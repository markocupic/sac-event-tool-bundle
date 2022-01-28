<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Date;
use Contao\Environment;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Haste\Form\Form;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CsvEventMemberExportController.
 *
 * @FrontendModule(CsvEventMemberExportController::TYPE, category="sac_event_tool_frontend_modules")
 */
class CsvEventMemberExportController extends AbstractFrontendModuleController
{
    public const TYPE = 'csv_event_member_export';

    /**
     * @var 
     */
    protected $objForm;

    /**
     * @var string
     */
    protected $strDelimiter = ';';

    /**
     * @var string
     */
    protected $strEnclosure = '"';

    /**
     * @var 
     */
    protected $defaultPassword;

    /**
     * @var array
     */
    protected $arrLines = [];

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;
        $services['request_stack'] = RequestStack::class;

        return $services;
    }

    /**
     * @throws Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->generateForm();
        $template->form = $this->objForm;
        $template->dateFormat = $this->get('contao.framework')->getAdapter(Config::class)->get('dateFormat');

        return $template->getResponse();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    private function generateForm(): void
    {
        $objForm = new Form(
            'form-event-member-export',
            'POST',
            function ($objHaste) {
                $request = $this->get('request_stack')->getCurrentRequest();

                return $request->get('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $environment = $this->get('contao.framework')->getAdapter(Environment::class);
        $objForm->setFormActionFromUri($environment->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('event-type', [
            'label' => ['Event-Typ auswählen', ''],
            'inputType' => 'select',
            'options' => ['all' => 'Alle Events', 'tour' => 'Tour', 'course' => 'Kurs'],
        ]);

        $objForm->addFormField('startDate', [
            'label' => 'Startdatum',
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true],
        ]);

        $objForm->addFormField('endDate', [
            'label' => 'Enddatum',
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true],
        ]);

        $objForm->addFormField('mountainguide', [
            'label' => ['Bergführer', 'Nur Events mit Bergführer exportieren'],
            'inputType' => 'checkbox',
            'eval' => [],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            $request = $this->get('request_stack')->getCurrentRequest();

            if ('form-event-member-export' === $request->get('FORM_SUBMIT')) {
                $eventType = $request->get('event-type');
                $arrFields = ['id', 'eventId', 'eventName', 'startDate', 'endDate', 'mainInstructor', 'mountainguide', 'eventState', 'executionState', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'bookingType', 'hasParticipated', 'stateOfSubscription', 'addedOn'];
                $startDate = strtotime($request->get('startDate'));
                $endDate = strtotime($request->get('endDate'));
                $this->getHeadline($arrFields);

                $statement1 = $this->get('database_connection')->executeQuery('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=? ORDER BY startDate', [$startDate, $endDate]);

                while (false !== ($objEvent = $statement1->fetch(\PDO::FETCH_OBJ))) {
                    if ('all' !== $eventType) {
                        if ($objEvent->eventType !== $eventType) {
                            continue;
                        }
                    }

                    if ($request->get('mountainguide')) {
                        if (!$objEvent->mountainguide) {
                            continue;
                        }
                    }

                    // Set tl_member.disable to true if member was not found in the csv-file
                    $statement2 = $this->get('database_connection')->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname', [$objEvent->id]);

                    while (false !== ($objEventMember = $statement2->fetch(\PDO::FETCH_OBJ))) {
                        $this->addLine($arrFields, $objEventMember);
                    }
                }
                $date = $this->get('contao.framework')->getAdapter(Date::class);

                $this->printCsv(sprintf('Event-Member-Export_%s.csv', $date->parse('Y-m-d')));
            }
        }

        $this->objForm = $objForm->generate();
    }

    /**
     * @param $arrFields
     * @param $objEventMember
     */
    private function addLine($arrFields, $objEventMember): void
    {
        $arrLine = [];

        foreach ($arrFields as $field) {
            $arrLine[] = $this->getField($field, $objEventMember);
        }

        $this->arrLines[] = $arrLine;
    }

    /**
     * @param $arrFields
     */
    private function getHeadline($arrFields): void
    {
        // Write headline
        $controller = $this->get('contao.framework')->getAdapter(Controller::class);
        $arrHeadline = [];

        foreach ($arrFields as $field) {
            if ('mainInstructor' === $field || 'mountainguide' === $field || 'startDate' === $field || 'endDate' === $field || 'eventState' === $field || 'executionState' === $field) {
                $controller->loadLanguageFile('tl_calendar_events');
                $arrHeadline[] = $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] ?? $field;
            } elseif ('phone' === $field) {
                $controller->loadLanguageFile('tl_member');
                $arrHeadline[] = $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field;
            } else {
                $controller->loadLanguageFile('tl_calendar_events_member');
                $arrHeadline[] = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field;
            }
        }
        $this->arrLines[] = $arrHeadline;
    }

    /**
     * @param $field
     * @param $objEventMember
     *
     * @return string
     */
    private function getField($field, $objEventMember)
    {
        $date = $this->get('contao.framework')->getAdapter(Date::class);
        $config = $this->get('contao.framework')->getAdapter(Config::class);
        $controller = $this->get('contao.framework')->getAdapter(Controller::class);
        $calendarEventsHelper = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModel = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        $memberModel = $this->get('contao.framework')->getAdapter(MemberModel::class);

        if ('password' === $field) {
            return '#######';
        }

        if ('addedOn' === $field) {
            return $date->parse('Y-m-d', $objEventMember->addedOn);
        }

        if ('dateOfBirth' === $field) {
            if (is_numeric($objEventMember->$field)) {
                return $date->parse($config->get('dateFormat'), $objEventMember->$field);
            }
        } elseif ('stateOfSubscription' === $field) {
            return $GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventMember->$field] ?? $objEventMember->$field;
        } elseif ('startDate' === $field) {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $date->parse('Y-m-d', $objEvent->startDate);
            }

            return '';
        } elseif ('endDate' === $field) {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $date->parse('Y-m-d', $objEvent->endDate);
            }

            return '';
        } elseif ('executionState' === $field) {
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] ?? $objEvent->$field;
            }

            return '';
        } elseif ('eventState' === $field) {
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] ?? $objEvent->$field;
            }

            return '';
        } elseif ('mainInstructor' === $field) {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $calendarEventsHelper->getMainInstructorName($objEvent);
            }

            return '';
        } elseif ('mountainguide' === $field) {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);

            if (null !== $objEvent) {
                return $objEvent->$field;
            }

            return '';
        } elseif ('phone' === $field) {
            $objMember = $memberModel->findOneBySacMemberId($objEventMember->sacMemberId);

            if (null !== $objMember) {
                return $objMember->$field;
            }

            return '';
        } else {
            return $objEventMember->{$field};
        }
    }

    /**
     * @param $filename
     *
     * @throws Exception
     */
    private function printCsv($filename): void
    {
        $arrData = $this->arrLines;

        // Convert special chars
        $arrFinal = [];

        foreach ($arrData as $arrRow) {
            $arrLine = array_map(
                static fn ($v) => html_entity_decode(htmlspecialchars_decode((string) $v)),
                $arrRow
            );
            $arrFinal[] = $arrLine;
        }

        // Load the CSV document from a string
        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);

        $csv->setDelimiter($this->strDelimiter);
        $csv->setEnclosure($this->strEnclosure);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Send file to browser
        $csv->output($filename);
        exit;
    }
}
