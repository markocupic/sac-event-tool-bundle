<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;


/**
 * Class ActivateMemberAccountController
 * @package Markocupic\SacEventToolBundle\Controller\ActivateMemberAccountController
 * @FrontendModule(category="sac_event_tool_fe_modules", type="activate_member_account")
 */
class ActivateMemberAccountController extends AbstractFrontendModuleController
{
    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Security
     */
    protected $security;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ScopeMatcher
     */
    protected $scopeMatcher;

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
     * ModuleSacEventToolActivateMemberAccount constructor.
     * @param ContaoFramework $framework
     * @param Connection $connection
     * @param Security $security
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, Connection $connection, Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

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
        if ($this->isFrontend())
        {
            // Set adapters
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
            $notificationAdapter = $this->framework->getAdapter(Notification::class);
            $urlAdapter = $this->framework->getAdapter(Url::class);
            $controllerAdapter = $this->framework->getAdapter(Controller::class);

            if (($objUser = $this->security->getUser()) instanceof FrontendUser)
            {
                $this->objUser = $objUser;
            }

            // Add groups
            $this->arrGroups = $stringUtilAdapter->deserialize($model->reg_groups, true);

            // Use terminal42/notification_center
            $this->objNotification = $notificationAdapter->findByPk($model->activateMemberAccountNotificationId);

            // Add step param
            if ($request->query->get('step') == '')
            {
                $url = $urlAdapter->addQueryString('step=1');
                $controllerAdapter->redirect($url);
            }

            $this->step = $request->query->get('step');
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
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
        $urlAdapter = $this->framework->getAdapter(Url::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $this->partial = new FrontendTemplate('partial_activate_member_account_step_' . $this->step);

        $this->partial->step = $this->step;
        $this->partial->hasError = false;
        $this->partial->errorMsg = '';

        switch ($this->step)
        {
            case 1:
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
        $urlAdapter = $this->framework->getAdapter(Url::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Get request
        $request = $this->requestStack->getCurrentRequest();

        $objForm = new Form('form-activate-member-account', 'POST', function ($objHaste) {
            $request = $this->requestStack->getCurrentRequest();
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
            'label'     => $GLOBALS['TL_LANG']['MSC']['activateMemberAccount_dateOfBirth'],
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
        $objWidget->addAttribute('placeholder', 'dd.mm.YYYY');

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Check for valid notification
            if (!$this->objNotification)
            {
                $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_noValidNotificationSelected'];
                $hasError = true;
            }

            // Validate sacMemberId
            $objMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute($request->request->get('username'));
            if (!$objMember->numRows)
            {
                $this->partial->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotAssignUserToSacMemberId'], $request->request->get('username'));
                $hasError = true;
            }

            if (!$hasError)
            {
                if ($dateAdapter->parse($configAdapter->get('dateFormat'), $objMember->dateOfBirth) !== $request->request->get('dateOfBirth'))
                {
                    $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndDateOfBirthDoNotMatch'];
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower($request->request->get('email')) !== "" && trim($objMember->email) == '')
                {
                    $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberEmailNotRegistered'];
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower($request->request->get('email')) !== strtolower($objMember->email))
                {
                    $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndEmailDoNotMatch'];
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->login)
                {
                    $this->partial->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdIsAllreadyRegistered'], $request->request->get('username'));
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->disable)
                {
                    $this->partial->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdHasBeendDeactivatedAndIsNoMoreValid'], $request->request->get('username'));
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
                        $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotTerminateActivationProcess'];
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
        $urlAdapter = $this->framework->getAdapter(Url::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        // Get request
        $request = $this->requestStack->getCurrentRequest();

        $objMember = $memberModelAdapter->findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMember === null)
        {
            $url = $urlAdapter->removeQueryString(['step']);
            $controllerAdapter->redirect($url);
        }
        $objForm = new Form('form-activate-member-account-activation-token', 'POST', function ($objHaste) {
            $request = $this->requestStack->getCurrentRequest();
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
                $this->partial->errorMsg = sprintf('Leider ist die Session abgelaufen. Starte den Aktivierungsprozess von vorne.<br><a href="%s">Aktivierungsprozess neu starten</a>', $url);
            }

            if ($objMember->disable)
            {
                $hasError = true;
                $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedAccountIsDeactivated'];
            }

            $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND activation=?')->limit(1)->execute($objMember->id, $token);

            if (!$hasError && !$objDb->numRows)
            {
                $hasError = true;
                $objMember->activationFalseTokenCounter++;
                $objMember->save();
                // Too many tries
                if ($objMember->activationFalseTokenCounter > 5)
                {
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;
                    $objMember->save();
                    unset($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                    $url = Url::removeQueryString(['step']);
                    $this->partial->doNotShowForm = true;
                    $this->partial->errorMsg = sprintf($GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedInvalidActivationCodeAndTooMuchTries'], '<br><a href="' . $url . '">', '</a>');
                }
                else
                {
                    // False token
                    $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_invalidActivationCode'];
                }
            }
            else
            {
                // Token has expired
                if ($objDb->activationLinkLifetime < time())
                {
                    $hasError = true;
                    $this->partial->doNotShowForm = true;
                    $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_activationCodeExpired'];
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
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $urlAdapter = $this->framework->getAdapter(Url::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        // Get request
        $request = $this->requestStack->getCurrentRequest();

        $objForm = new Form('form-activate-member-account-set-password', 'POST', function ($objHaste) {
            $request = $this->requestStack->getCurrentRequest();
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
            $this->partial->errorMsg = $GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sessionExpired'];
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

                    // Set sesion data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 4;

                    // Log
                    System::log(sprintf('User %s %s [%s] has successfully activated her/his member account.', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->sacMemberId), __METHOD__, 'MEMBER_ACCOUNT_ACTIVATION');

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

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend(): bool
    {
        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
    }

}
