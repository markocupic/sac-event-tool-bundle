<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey\Controller\ContentElement;

use Codefog\HasteBundle\Form\Form;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\StringUtil;
use Markocupic\ContaoFrontendUserNotification\Notification\DefaultFrontendUserNotification;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyAboType;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyEligibility;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyPeriod;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Abo survey: logged in members choose an abo (White Risk or SchweizMobil) for the year
 * set in the content element. The form is open between aboSurveyStartDate and aboSurveyEndDate.
 * The survey can be submitted only once per year; afterwards the member sees his entry and
 * the date of the submission instead of the form. Guests do not see the element at all.
 * Only instructors of a released tour or course in the survey year may fill in the form
 * (see AboSurveyEligibility); everybody else gets a message with the reason.
 *
 * See docs/features/abo-survey.md
 */
#[AsContentElement(AboSurveyController::TYPE, category: 'sac_event_tool_content_elements')]
class AboSurveyController extends AbstractContentElementController
{
    public const string TYPE = 'abo_survey';

    public const string NOTIFICATION_KEY = 'abo_survey_controller::saved';

    public function __construct(
        private readonly Security $security,
        private readonly AboSurveyEligibility $aboSurveyEligibility,
        private readonly AboSurveyRepository $aboSurveyRepository,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $user = $this->security->getUser();

        // Only visible for logged in frontend users
        if (!$user instanceof FrontendUser) {
            return new Response('');
        }

        $year = (int) $model->aboSurveyYear;
        $state = AboSurveyPeriod::getState((int) $model->aboSurveyStartDate, (int) $model->aboSurveyEndDate, time());
        $entry = (int) $user->sacMemberId > 0 ? $this->aboSurveyRepository->findOneBySacMemberIdAndYear((int) $user->sacMemberId, $year) : null;

        $template->set('user', $user->getData());
        $template->set('year', $year);
        $template->set('start_date', (int) $model->aboSurveyStartDate);
        $template->set('end_date', (int) $model->aboSurveyEndDate);
        $template->set('state', $state);
        $template->set('entry', $entry);
        $template->set('entry_abo_label', null === $entry ? null : $this->getAboTypeLabel($entry['aboType']));
        $template->set('entry_previous_abo_label', empty($entry['previousAboType']) ? null : $this->getAboTypeLabel($entry['previousAboType']));
        $template->set('notification_key', self::NOTIFICATION_KEY);
        $template->set('form', null);
        $template->set('ineligibility_reason', null);

        // A member can submit the survey only once. Afterwards he sees his entry instead of the form.
        if (null !== $entry) {
            return $template->getResponse();
        }

        $ineligibilityReason = $this->aboSurveyEligibility->check(
            (int) $user->sacMemberId,
            $year,
            StringUtil::deserialize($model->aboSurveyAllowedUserRoles, true),
            StringUtil::deserialize($model->aboSurveyAllowedUsers, true),
        );

        if (null !== $ineligibilityReason) {
            $template->set('ineligibility_reason', $ineligibilityReason);

            return $template->getResponse();
        }

        if (AboSurveyPeriod::STATE_OPEN === $state) {
            $template->set('form', $this->getForm($request, $user, $year));
        }

        return $template->getResponse();
    }

    private function getForm(Request $request, FrontendUser $user, int $year): Form
    {
        $form = new Form('form-abo-survey-'.$year, Request::METHOD_POST);

        $form->addContaoHiddenFields();
        $form->setAction($request->getUri());

        $options = [];

        foreach (AboSurveyAboType::ALL as $aboType) {
            $options[$aboType] = $this->getAboTypeLabel($aboType);
        }

        $eventOrganizers = $this->aboSurveyRepository->findEventOrganizerTitles();

        $form->addFormField('eventOrganizer', [
            'label' => $this->translator->trans('MSC.abo_survey_event_organizer', [], 'contao_default'),
            'inputType' => 'select',
            'options' => array_combine($eventOrganizers, $eventOrganizers),
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'class' => 'form-select'],
        ]);

        $form->addFormField('previousAboType', [
            'label' => $this->translator->trans('MSC.abo_survey_previous_abo_type', [], 'contao_default'),
            'inputType' => 'select',
            'options' => $options,
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'class' => 'form-select'],
        ]);

        $form->addFormField('aboType', [
            'label' => $this->translator->trans('MSC.abo_survey_abo_type', [], 'contao_default'),
            'inputType' => 'select',
            'options' => $options,
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'class' => 'form-select'],
        ]);

        $form->addFormField('notice', [
            'label' => $this->translator->trans('MSC.abo_survey_notice', [], 'contao_default'),
            'inputType' => 'textarea',
            'eval' => ['mandatory' => false, 'maxlength' => 2000, 'decodeEntities' => true, 'rows' => 4, 'class' => 'form-control'],
        ]);

        $form->addFormField('submit', [
            'label' => $this->translator->trans('MSC.abo_survey_submit', [], 'contao_default'),
            'inputType' => 'submit',
            'eval' => ['class' => 'btn btn-primary'],
        ]);

        if ($form->validate()) {
            $eventOrganizer = (string) $form->fetch('eventOrganizer');
            $previousAboType = (string) $form->fetch('previousAboType');
            $aboType = (string) $form->fetch('aboType');
            $notice = trim((string) $form->fetch('notice'));

            if (\in_array($eventOrganizer, $eventOrganizers, true) && \in_array($previousAboType, AboSurveyAboType::ALL, true) && \in_array($aboType, AboSurveyAboType::ALL, true)) {
                $this->aboSurveyRepository->save($user, $year, $eventOrganizer, $previousAboType, $aboType, $notice);

                new DefaultFrontendUserNotification(
                    $user,
                    self::NOTIFICATION_KEY,
                    $this->translator->trans('MSC.abo_survey_saved_title', [], 'contao_default'),
                    $this->translator->trans('MSC.abo_survey_saved_text', [$this->getAboTypeLabel($aboType), $year], 'contao_default'),
                    time() + 60,
                );

                $this->contaoGeneralLogger?->info(
                    \sprintf(
                        'Frontend user %s %s (SAC member id %d) has chosen the abo "%s" (previous abo "%s") for %d in the abo survey.',
                        $user->firstname,
                        $user->lastname,
                        $user->sacMemberId,
                        $aboType,
                        $previousAboType,
                        $year,
                    ),
                    ['contao' => new ContaoContext(__METHOD__, Log::ABO_SURVEY_SUBMIT)],
                );

                throw new RedirectResponseException($request->getUri());
            }
        }

        return $form;
    }

    private function getAboTypeLabel(string $aboType): string
    {
        return $this->translator->trans('tl_abo_survey.'.$aboType, [], 'contao_tl_abo_survey');
    }
}
