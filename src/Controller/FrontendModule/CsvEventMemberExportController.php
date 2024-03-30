<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\Environment;
use Contao\MemberModel;
use Contao\ModuleModel;
use Doctrine\DBAL\Connection;
use League\Csv\ByteSequence;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(CsvEventMemberExportController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_csv_event_member_export')]
class CsvEventMemberExportController extends AbstractFrontendModuleController
{
    public const TYPE = 'csv_event_member_export';

    private string $strDelimiter = ';';
    private string $strEnclosure = '"';
    private array $arrLines = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

	/**
	 * @param FragmentTemplate $template
	 * @param ModuleModel $model
	 * @param Request $request
	 * @return Response
	 * @throws Exception
	 * @throws InvalidArgument
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \League\Csv\CannotInsertRecord
	 */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $form = $this->getForm($request);
        $template->set('form', $form->generate());
        $template->set('dateFormat', $this->framework->getAdapter(Config::class)->get('dateFormat'));

        return $template->getResponse();
    }

	/**
	 * @param Request $request
	 * @return Form
	 * @throws Exception
	 * @throws InvalidArgument
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \League\Csv\CannotInsertRecord
	 */
    private function getForm(Request $request): Form
    {
        $objForm = new Form(
            'form-event-member-export',
            'POST',
        );

        $environment = $this->framework->getAdapter(Environment::class);
        $objForm->setAction($environment->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('event-type', [
            'label' => ['Event-Typ auswählen', ''],
            'inputType' => 'select',
            'options' => ['all' => 'Alle Events', EventType::TOUR => 'Tour', EventType::COURSE => 'Kurs'],
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
            if ('form-event-member-export' === $request->request->get('FORM_SUBMIT')) {
                $eventType = $request->request->get('event-type');
                $arrFields = ['id', 'eventId', 'eventName', 'startDate', 'endDate', 'mainInstructor', 'mountainguide', 'eventState', 'executionState', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'bookingType', 'hasParticipated', 'stateOfSubscription', 'dateAdded'];
                $startDate = strtotime($request->request->get('startDate'));
                $endDate = strtotime($request->request->get('endDate'));

                // Add the headline first
                $this->getHeadline($arrFields);

                $statement1 = $this->connection->executeQuery('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=? ORDER BY startDate', [$startDate, $endDate]);

                while (false !== ($arrEvent = $statement1->fetchAssociative())) {
                    if ('all' !== $eventType) {
                        if ($arrEvent['eventType'] !== $eventType) {
                            continue;
                        }
                    }

                    if ($request->request->get('mountainguide')) {
                        if (EventMountainGuide::NO_MOUNTAIN_GUIDE === $arrEvent['mountainguide']) {
                            continue;
                        }
                    }

                    $statement2 = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname', [$arrEvent['id']]);

                    while (false !== ($arrEventMember = $statement2->fetchAssociative())) {
                        $this->addLine($arrFields, $arrEventMember);
                    }
                }

                $this->printCsv(sprintf('Event-Member-Export_%s.csv', date('Y-m-d')));
            }
        }

        return $objForm;
    }

	/**
	 * @param array $arrFields
	 * @param array $arrEventMember
	 * @return void
	 */
    private function addLine(array $arrFields, array $arrEventMember): void
    {
        $arrLine = [];

        foreach ($arrFields as $field) {
            $arrLine[] = $this->getField($field, $arrEventMember);
        }

        $this->arrLines[] = $arrLine;
    }

	/**
	 * @param array $arrFields
	 * @return void
	 */
    private function getHeadline(array $arrFields): void
    {
        // Write headline
        $controller = $this->framework->getAdapter(Controller::class);
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
	 * @param string $field
	 * @param array $arrEventMember
	 * @return string
	 */
    private function getField(string $field, array $arrEventMember): string
    {
        $date = $this->framework->getAdapter(Date::class);
        $config = $this->framework->getAdapter(Config::class);
        $controller = $this->framework->getAdapter(Controller::class);
        $calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $memberModel = $this->framework->getAdapter(MemberModel::class);

        $value = '';

        if ('password' === $field) {
            $value = '#######';
        }

        if ('dateAdded' === $field) {
            $value = $date->parse('Y-m-d', $arrEventMember['dateAdded']);
        }

        if ('dateOfBirth' === $field) {
            if (is_numeric($arrEventMember[$field])) {
                $value = $date->parse($config->get('dateFormat'), $arrEventMember[$field]);
            }
        } elseif ('stateOfSubscription' === $field) {
            $value = $GLOBALS['TL_LANG']['MSC'][$arrEventMember[$field]] ?? $arrEventMember[$field];
        } elseif ('startDate' === $field) {
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $date->parse('Y-m-d', $objEvent->startDate);
            }
        } elseif ('endDate' === $field) {
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $date->parse('Y-m-d', $objEvent->endDate);
            }
        } elseif ('executionState' === $field) {
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] ?? $objEvent->$field;
            }
        } elseif ('eventState' === $field) {
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] ?? $objEvent->$field;
            }
        } elseif ('mainInstructor' === $field) {
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $calendarEventsHelper->getMainInstructorName($objEvent);
            }
        } elseif ('mountainguide' === $field) {
            $objEvent = $calendarEventsModel->findByPk($arrEventMember['eventId']);

            if (null !== $objEvent) {
                $value = $GLOBALS['TL_LANG']['MSC']['event_mountainguide'][$objEvent->$field];
            }
        } elseif ('phone' === $field) {
            $objMember = $memberModel->findOneBySacMemberId($arrEventMember['sacMemberId']);

            if (null !== $objMember) {
                $value = $objMember->$field;
            }
        } else {
            $value = $arrEventMember[$field];
        }

        return (string) $value;
    }

	/**
	 * @param string $filename
	 * @return void
	 * @throws Exception
	 * @throws InvalidArgument
	 * @throws \League\Csv\CannotInsertRecord
	 */
    private function printCsv(string $filename): void
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
        $csv->setOutputBOM(ByteSequence::BOM_UTF8);

        $csv->setDelimiter($this->strDelimiter);
        $csv->setEnclosure($this->strEnclosure);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Send file to browser
        $csv->output($filename);
        exit;
    }
}
