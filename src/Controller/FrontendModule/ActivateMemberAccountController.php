<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use Haste\Util\Url;
use NotificationCenter\Model\Notification;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class ActivateMemberAccountController
 * @package Markocupic\SacEventToolBundle\Controller\ActivateMemberAccountController
 * @FrontendModule(category="sac_event_tool_frontend_modules", type="activate_member_account")
 */
class ActivateMemberAccountController extends AbstractFrontendModuleController
{

    /**
     * @var integer
     */
    protected $step;

    /**
     * @var Notification
     */
    protected $objNotification;

    /**
     * @var array
     */
    protected $arrGroups;

    /**
     * @var Form
     */
    protected $objForm;

    /**
     * @var FrontendTemplate
     */
    protected $partial;

    /**
     * @var integer
     */
    protected $activationLinkLifetime = 3600;

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Return empty string, if user is not logged in as a frontend user

        // Set adapters
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        $notificationAdapter = $this->get('contao.framework')->getAdapter(Notification::class);
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $this->objUser = $objUser;
        }

        // Get groups from model
        $this->arrGroups = $stringUtilAdapter->deserialize($model->reg_groups, true);

        // Use terminal42/notification_center
        $this->objNotification = $notificationAdapter->findByPk($model->activateMemberAccountNotificationId);

        // Redirect to first step, if there is no step param set in the url
        if ($request->query->get('step') == '' || !is_numeric($request->query->get('step')))
        {
            $url = $urlAdapter->addQueryString('step=1');
            $controllerAdapter->redirect($url);
        }

        // Get the step number from url
        $this->step = $request->query->get('step');

        // Call parent __invoke
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;
        $services['security.helper'] = Security::class;
        $services['request_stack'] = RequestStack::class;
        $services['translator'] = TranslatorInterface::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Set Adapters
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);

        // Instantiate partial template
        $this->partial = new FrontendTemplate('partial_activate_member_account_step_' . $this->step);
        $this->partial->step = $this->step;
        $this->partial->hasError = false;
        $this->partial->errorMsg = '';

        switch ($this->step)
        {
            case 1:
                // Get session
                $session = System::getContainer()->get('session');
                $flashBag = $session->getFlashBag();

                // Get error message from the login form if there was a redirect because the account is not activated
                if ($session->isStarted() && $flashBag->has('mod_login'))
                {
                    $arrMessages = $flashBag->get('mod_login');
                    $this->partial->hasError = true;
                    $this->partial->errorMsg = implode('<br>', $arrMessages);
                }

                unset($_SESSION['SAC_EVT_TOOL']);
                $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 1;

                $this->generateFirstForm();

                if ($this->objForm !== null)
                {
                    $this->partial->form = $this->objForm->generate();
                }
                break;

            case 2:
                $objMember = $memberModelAdapter->findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] === 2 && $objMember !== null)
                {
                    $this->generateSecondForm();
                    $this->partial->objMember = $objMember;
                    $this->partial->form = $this->objForm->generate();
                }
                else
                {
                    unset($_SESSION['SAC_EVT_TOOL']);
                    $url = $urlAdapter->removeQueryString(['step']);
                    $controllerAdapter->redirect($url);
                }
                break;

            case 3:
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] === 3)
                {
                    $this->generateThirdForm();
                    if ($this->objForm !== null)
                    {
                        $this->partial->form = $this->objForm->generate();
                    }
                }
                else
                {
                    unset($_SESSION['SAC_EVT_TOOL']);
                    $url = $urlAdapter->removeQueryString(['step']);
                    $controllerAdapter->redirect($url);
                }
                break;

            case 4:
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] !== 4)
                {
                    $url = $urlAdapter->removeQueryString(['step']);
                    $controllerAdapter->redirect($url);
                }
                unset($_SESSION['SAC_EVT_TOOL']);
                break;
        }

        $template->partial = $this->partial->parse();
        return $template->getResponse();
    }

    /**
     * Generate first form
     */
    protected function generateFirstForm(): void
    {
        // Set adapters
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        // Get translator
        $translator = $this->get('translator');

        // Get request
        $request = $this->get('request_stack')->getCurrentRequest();

        $objForm = new Form('form-activate-member-account', 'POST', function ($objHaste) {
            $request = $this->get('request_stack')->getCurrentRequest();
            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });
        $url = $environmentAdapter->get('uri');
        $objForm->setFormActionFromUri($url);

        $objForm->addFormField('username', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_sacMemberId'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('email', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_email'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email'),
        ));
        $objForm->addFormField('dateOfBirth', array(
            'label'     => sprintf($GLOBALS['TL_LANG']['MSC']['activateMemberAccount_dateOfBirth'], $configAdapter->get('dateFormat')),
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'rgxp' => 'date', 'datepicker' => true),
        ));
        $objForm->addFormField('agb', array(
            'label'     => array('', sprintf($GLOBALS['TL_LANG']['MSC']['activateMemberAccount_agb'], '<a href="#" data-toggle="modal" data-target="#agbModal">', '</a>')),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => true),
        ));
        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_startActivationProcess'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        $objWidget = $objForm->getWidget('dateOfBirth');
        $objWidget->addAttribute('placeholder', $configAdapter->get('dateFormat'));

        // Add dateFormat to the template
        $this->partial->dateFormat = $configAdapter->get('dateFormat');

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Check for valid notification
            if (!$this->objNotification)
            {
                $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_noValidNotificationSelected', [], 'contao_default');
                $hasError = true;
            }

            // Validate sacMemberId
            $objMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute($request->request->get('username'));
            if (!$objMember->numRows)
            {
                $this->partial->errorMsg = sprintf($translator->trans('ERR.activateMemberAccount_couldNotAssignUserToSacMemberId', [], 'contao_default'), $request->request->get('username'));
                $hasError = true;
            }

            if (!$hasError)
            {
                if ($dateAdapter->parse($configAdapter->get('dateFormat'), $objMember->dateOfBirth) !== $request->request->get('dateOfBirth'))
                {
                    $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_sacMemberIdAndDateOfBirthDoNotMatch', [], 'contao_default');
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower($request->request->get('email')) !== "" && trim($objMember->email) == '')
                {
                    $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_sacMemberEmailNotRegistered', [], 'contao_default');
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower($request->request->get('email')) !== strtolower($objMember->email))
                {
                    $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_sacMemberIdAndEmailDoNotMatch', [], 'contao_default');
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->login)
                {
                    $this->partial->errorMsg = sprintf($translator->trans('ERR.activateMemberAccount_accountWithThisSacMemberIdIsAllreadyRegistered', [], 'contao_default'), $request->request->get('username'));
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->disable)
                {
                    $this->partial->errorMsg = sprintf($translator->trans('ERR.activateMemberAccount_accountWithThisSacMemberIdHasBeendDeactivatedAndIsNoMoreValid', [], 'contao_default'), $request->request->get('username'));
                    $hasError = true;
                }
            }

            $this->partial->hasError = $hasError;

            // Save data to tl_member
            if (!$hasError)
            {
                $objMemberModel = $memberModelAdapter->findByPk($objMember->id);
                if ($objMemberModel !== null)
                {
                    $token = rand(111111, 999999);
                    $objMemberModel->activation = $token;
                    $objMemberModel->activationLinkLifetime = time() + $this->activationLinkLifetime;
                    $objMemberModel->activationFalseTokenCounter = 0;
                    $objMemberModel->save();

                    if ($this->notifyMember($objMemberModel))
                    {
                        // Set session dataR
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId'] = $objMemberModel->id;
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 2;

                        // Redirect
                        $url = $urlAdapter->removeQueryString(['step']);
                        $url = $urlAdapter->addQueryString('step=2', $url);
                        $controllerAdapter->redirect($url);
                    }
                    else
                    {
                        $hasError = true;
                        $this->partial->hasError = $hasError;
                        $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_couldNotTerminateActivationProcess', [], 'contao_default');
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * Generate second form
     */
    protected function generateSecondForm(): void
    {
        // Set adapters
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        // Get translator
        $translator = $this->get('translator');

        // Get request
        $request = $this->get('request_stack')->getCurrentRequest();

        $objMember = $memberModelAdapter->findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMember === null)
        {
            $url = $urlAdapter->removeQueryString(['step']);
            $controllerAdapter->redirect($url);
        }
        $objForm = new Form('form-activate-member-account-activation-token', 'POST', function ($objHaste) {
            $request = $this->get('request_stack')->getCurrentRequest();
            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Password
        $objForm->addFormField('activationToken', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterTheActivationCode'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'minlength' => 6, 'maxlength' => 6),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_proceedActivationProcess'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Check activation token
        $hasError = false;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $request->request->get('activationToken') !== '')
        {
            $token = trim($request->request->get('activationToken'));

            $objMember = $memberModelAdapter->findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
            if ($objMember === null)
            {
                $hasError = true;
                $url = $urlAdapter->removeQueryString(['step']);
                $this->partial->doNotShowForm = true;
                $this->partial->errorMsg = sprintf($translator->trans('ERR.activateMemberAccount_sessionExpiredPleaseTestartProcess', [], 'contao_default'), $url);
            }

            if ($objMember->disable)
            {
                $hasError = true;
                $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_accountActivationStoppedAccountIsDeactivated', [], 'contao_default');
            }

            $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND activation=?')->limit(1)->execute($objMember->id, $token);

            if (!$hasError && !$objDb->numRows)
            {
                $hasError = true;
                $objMember->activationFalseTokenCounter++;
                $objMember->save();

                // Limit tries to 5x
                if ($objMember->activationFalseTokenCounter > 5)
                {
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;
                    $objMember->save();
                    unset($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                    $url = $urlAdapter->removeQueryString(['step']);
                    $this->partial->doNotShowForm = true;
                    $this->partial->errorMsg = sprintf($translator->trans('ERR.activateMemberAccount_accountActivationStoppedInvalidActivationCodeAndTooMuchTries', [], 'contao_default'), '<br><a href="' . $url . '">', '</a>');
                }
                else
                {
                    // False token
                    $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_invalidActivationCode', [], 'contao_default');
                }
            }
            else
            {
                // Token has expired
                if ($objDb->activationLinkLifetime < time())
                {
                    $hasError = true;
                    $this->partial->doNotShowForm = true;
                    $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_activationCodeExpired', [], 'contao_default');
                }
                else
                {
                    // All ok!
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;

                    // Set session data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 3;

                    // Redirect
                    $url = $urlAdapter->removeQueryString(['step']);
                    $url = $urlAdapter->addQueryString('step=3', $url);
                    $controllerAdapter->redirect($url);
                }
            }
        }

        $this->partial->hasError = $hasError;
        $this->objForm = $objForm;
    }

    /**
     * Generate third form
     */
    protected function generateThirdForm(): void
    {
        // Set adapters
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        // Get translator
        /** @var TranslatorInterface $translator */
        $translator = $this->get('translator');

        // Get request
        $request = $this->get('request_stack')->getCurrentRequest();

        $objForm = new Form('form-activate-member-account-set-password', 'POST', function ($objHaste) {
            $request = $this->get('request_stack')->getCurrentRequest();
            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Password
        $objForm->addFormField('password', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterPassword'],
            'inputType' => 'password',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_activateMemberAccount'],
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Check activation token
        $hasError = false;

        // Validate session
        $objMemberModel = $memberModelAdapter->findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMemberModel === null)
        {
            $this->partial->errorMsg = $translator->trans('ERR.activateMemberAccount_sessionExpired', [], 'contao_default');
            $hasError = true;
        }

        $this->partial->hasError = $hasError;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $hasError === false)
        {
            // Save data to tl_member
            if (!$hasError)
            {
                if ($objMemberModel !== null)
                {
                    $objMemberModel->password = password_hash($request->request->get('password'), PASSWORD_DEFAULT);
                    $objMemberModel->activation = '';
                    $objMemberModel->activationLinkLifetime = 0;
                    $objMemberModel->activationFalseTokenCounter = 0;
                    $objMemberModel->login = '1';

                    // Add groups
                    $arrGroups = $stringUtilAdapter->deserialize($objMemberModel->groups, true);
                    $arrGroups = array_merge($arrGroups, $this->arrGroups);
                    $arrGroups = array_unique($arrGroups);
                    $arrGroups = array_filter($arrGroups);
                    $objMemberModel->groups = serialize($arrGroups);
                    $objMemberModel->save();

                    // Set session data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 4;

                    // Log
                    $logger = System::getContainer()->get('monolog.logger.contao');
                    $strText = sprintf('User %s %s [%s] has successfully activated her/his member account.', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->sacMemberId);
                    $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, 'MEMBER_ACCOUNT_ACTIVATION')));

                    // Redirect
                    $url = $urlAdapter->removeQueryString(['step']);
                    $url = $urlAdapter->addQueryString('step=4', $url);
                    $controllerAdapter->redirect($url);
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @param MemberModel $objMember
     * @return bool
     */
    private function notifyMember(MemberModel $objMember): bool
    {
        // Use terminal42/notification_center
        if ($this->objNotification !== null)
        {
            // Set token array
            $arrTokens = array(
                'firstname'   => html_entity_decode($objMember->firstname),
                'lastname'    => html_entity_decode($objMember->lastname),
                'street'      => html_entity_decode($objMember->street),
                'postal'      => html_entity_decode($objMember->postal),
                'city'        => html_entity_decode($objMember->city),
                'phone'       => html_entity_decode($objMember->phone),
                'activation'  => $objMember->activation,
                'username'    => html_entity_decode($objMember->username),
                'sacMemberId' => html_entity_decode($objMember->username),
                'email'       => $objMember->email,
            );

            $this->objNotification->send($arrTokens, 'de');

            return true;
        }
        return false;
    }

}
