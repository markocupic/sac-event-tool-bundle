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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

use Codefog\HasteBundle\Form\Form;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the event registration form. The fields depend on the event
 * (journey, AHV number, multi-day event, mountain guide offer, code of conduct).
 */
class EventRegistrationFormFactory
{
    // Fields that are prefilled with the data of the member (tl_member)
    private const array MEMBER_PRESET_FIELDS = ['phone', 'mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits', 'ahvNumber'];

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly CarSeatInfo $carSeatInfo,
        private readonly ContaoFramework $framework,
        private readonly TicketInfo $ticketInfo,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function create(CalendarEventsModel $eventModel, MemberModel $memberModel, Request $request): Form
    {
        $form = $this->createForm('form-event-registration', 'POST');
        $form->setAction($request->getUri());

        foreach ($this->getFieldNames($eventModel) as $field) {
            $form->addFormField($field, $this->getFormFieldConfig($field, $eventModel));
        }

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields. DO NOT use this
        // method with generate() as the "form" template provides those fields by default.
        $form->addContaoHiddenFields();

        foreach (self::MEMBER_PRESET_FIELDS as $field) {
            if ($form->hasFormField($field)) {
                $widget = $form->getWidget($field);

                if (empty($widget->value)) {
                    $widget->value = $memberModel->{$field};
                }
            }
        }

        return $form;
    }

    /**
     * @return list<string>
     */
    public function getFieldNames(CalendarEventsModel $eventModel): array
    {
        $fields = [];

        $journey = $this->framework->getAdapter(CalendarEventsJourneyModel::class)->findById($eventModel->journey);

        if ('public-transport' === $journey?->alias) {
            $fields[] = 'ticketInfo';
        }

        if ('car' === $journey?->alias) {
            $fields[] = 'carInfo';
        }

        if ($eventModel->askForAhvNumber) {
            $fields[] = 'ahvNumber';
        }

        $fields[] = 'mobile';
        $fields[] = 'emergencyPhone';
        $fields[] = 'emergencyPhoneName';
        $fields[] = 'notes';

        // Only ask for food habits if it is a multi-day event
        if ('generalEvent' === $eventModel->eventType || $this->isMultiDayEvent($eventModel)) {
            $fields[] = 'foodHabits';
        }

        $fields[] = EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER === $eventModel->mountainguide ? 'avbSbv' : 'agb';

        // The participant must confirm that they have read and understood the event requirements
        // and possess the necessary skills.
        if ('' !== $this->getCodeOfConductSRC($eventModel)) {
            $fields[] = 'hasAcknowledgedEventRequirements';
        }

        $fields[] = 'hasAcceptedPrivacyRules';
        $fields[] = 'submit';

        return $fields;
    }

    /**
     * An event with several dates on consecutive days (e.g. 3 dates from Friday to Sunday).
     */
    public function isMultiDayEvent(CalendarEventsModel $eventModel): bool
    {
        return self::areConsecutiveDays(
            $this->calendarEventsUtil->getStartTstamp($eventModel),
            $this->calendarEventsUtil->getEndTstamp($eventModel),
            \count($this->calendarEventsUtil->getEventTimestamps($eventModel)),
        );
    }

    /**
     * Compares calendar days instead of seconds: a day with a daylight saving time change
     * has 23 or 25 hours, so "start + n * 86400 = end" would fail.
     */
    public static function areConsecutiveDays(int $startDate, int $endDate, int $numberOfDates): bool
    {
        if ($numberOfDates < 2) {
            return false;
        }

        $start = (new \DateTimeImmutable())->setTimestamp($startDate)->setTime(0, 0);
        $end = (new \DateTimeImmutable())->setTimestamp($endDate)->setTime(0, 0);

        return $start < $end && $start->diff($end)->days === $numberOfDates - 1;
    }

    /**
     * Test seam: creates the Haste form instance.
     */
    protected function createForm(string $id, string $method): Form
    {
        return new Form($id, $method);
    }

    private function getFormFieldConfig(string $field, CalendarEventsModel $eventModel): array
    {
        return match ($field) {
            'ticketInfo' => [
                'label' => $this->translator->trans('FORM.evt_reg_ticketInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $this->ticketInfo->getAll(),
                'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => $this->translator->trans('FORM.evt_reg_blankLabelTicketInfo', [], 'contao_default'), 'mandatory' => true],
            ],
            'carInfo' => [
                'label' => $this->translator->trans('FORM.evt_reg_carInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $this->carSeatInfo->getAll(),
                'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => $this->translator->trans('FORM.evt_reg_blankLabelCarInfo', [], 'contao_default'), 'mandatory' => true],
            ],
            'ahvNumber' => [
                'label' => $this->translator->trans('FORM.evt_reg_ahvNumber', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 16, 'rgxp' => 'ahv', 'placeholder' => '756.7086.3589.03'],
            ],
            'mobile' => [
                'label' => $this->translator->trans('FORM.evt_reg_mobile', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone'],
            ],
            'emergencyPhone' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhone', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone'],
            ],
            'emergencyPhoneName' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhoneName', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 250],
            ],
            'notes' => [
                'label' => $this->translator->trans('FORM.evt_reg_notes', [], 'contao_default'),
                'inputType' => 'textarea',
                'eval' => ['mandatory' => true, 'maxlength' => 2000, 'rows' => 4],
                'class' => '',
            ],
            'foodHabits' => [
                'label' => $this->translator->trans('FORM.evt_reg_foodHabits', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'maxlength' => 5000],
            ],
            'agb' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_agb', [], 'contao_default')],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'avbSbv' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_avbSbv', [$this->getAvbSvbUrl($eventModel)], 'contao_default')],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'hasAcknowledgedEventRequirements' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_hasAcknowledgedEventRequirements', [$this->getCodeOfConductSRC($eventModel)], 'contao_default')],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'hasAcceptedPrivacyRules' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_hasAcceptedPrivacyRules', [], 'contao_default')],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'submit' => [
                'label' => $this->translator->trans('FORM.evt_reg_submit', [], 'contao_default'),
                'inputType' => 'submit',
            ],
        };
    }

    private function getAvbSvbUrl(CalendarEventsModel $eventModel): string
    {
        $organizers = $this->calendarEventsUtil->getEventOrganizerModels($eventModel);

        if (empty($organizers)) {
            return '';
        }

        return (string) $organizers[0]->avbSvbUrl;
    }

    private function getCodeOfConductSRC(CalendarEventsModel $eventModel): string
    {
        $organizers = $this->calendarEventsUtil->getEventOrganizerModels($eventModel);

        if (empty($organizers) || empty($organizers[0]->codeOfConductSRC)) {
            return '';
        }

        return StringUtil::binToUuid($organizers[0]->codeOfConductSRC);
    }
}
