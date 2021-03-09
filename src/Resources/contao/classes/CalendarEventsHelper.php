<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentGallery;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\Events;
use Contao\EventTypeModel;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendTemplate;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\TourDifficultyModel;
use Contao\TourTypeModel;
use Contao\UserModel;
use Haste\Util\Url;

/**
 * Class CalendarEventsHelper
 */
class CalendarEventsHelper
{
	/**
	 * @param CalendarEventsModel $objEvent
	 * @param $strProperty
	 * @param  null                                            $objTemplate
	 * @return array|bool|CalendarEventsModel|int|mixed|string
	 * @throws \Exception
	 */
	public static function getEventData(CalendarEventsModel $objEvent, $strProperty, $objTemplate = null)
	{
		// Load language files
		Controller::loadLanguageFile('tl_calendar_events');
		Controller::loadLanguageFile('default');
		$value = '';

		// eventImage||5
		$arrProperty = explode('||', $strProperty);

		switch ($arrProperty[0])
		{
			case 'model':
				$value = $objEvent;
				break;
			case 'id':
				$value = $objEvent->id;
				break;
			case 'eventId':
				$value = sprintf('%s-%s', Date::parse('Y', $objEvent->startDate), $objEvent->id);
				break;
			case 'eventTitle':
				$value = Controller::replaceInsertTags(sprintf('{{event_title::%s}}', $objEvent->id));
				break;
			case 'eventUrl':
				$value = Controller::replaceInsertTags(sprintf('{{event_url::%s}}', $objEvent->id));
				break;
			case 'tourTypesIds':
				$value = implode('', StringUtil::deserialize($objEvent->tourType, true));
				break;
			case 'tourTypesShortcuts':
				$value = implode(' ', static::getTourTypesAsArray($objEvent, 'shortcut', true));
				break;
			case 'tourTypesTitles':
				$value = implode('<br>', static::getTourTypesAsArray($objEvent, 'title', false));
				break;
			case 'startDateDay':
				$value = Date::parse('d', $objEvent->startDate);
				break;
			case 'startDateMonth':
				$value = Date::parse('M', $objEvent->startDate);
				break;
			case 'startDateYear':
				$value = Date::parse('y', $objEvent->startDate);
				break;
			case 'endDateDay':
				$value = Date::parse('d', $objEvent->endDate);
				break;
			case 'endDateMonth':
				$value = Date::parse('M', $objEvent->endDate);
				break;
			case 'endDateYear':
				$value = Date::parse('y', $objEvent->endDate);
				break;
			case 'eventPeriodSm':
				$value = static::getEventPeriod($objEvent, 'd.m.Y', false);
				break;
			case 'eventPeriodSmTooltip':
				$value = static::getEventPeriod($objEvent, 'd.m.Y', false, true);
				break;
			case 'eventPeriodLgInline':
				$value = static::getEventPeriod($objEvent, 'D, d.m.Y', false, true, true);
				break;
			case 'eventPeriodLg':
				$value = static::getEventPeriod($objEvent, 'D, d.m.Y', false);
				break;
			case 'eventPeriodLgTooltip':
				$value = static::getEventPeriod($objEvent, 'D, d.m.Y', false, true);
				break;
			case 'eventDuration':
				$value = static::getEventDuration($objEvent);
				break;
			case 'registrationStartDateFormated':
				$value = Date::parse(Config::get('dateFormat'), $objEvent->registrationStartDate);
				break;
			case 'registrationEndDateFormated':
				// If registration end time! is set to default --> 23:59 then only show registration end date!
				$endDate = Date::parse(Config::get('dateFormat'), $objEvent->registrationEndDate);

				if (abs($objEvent->registrationEndDate - strtotime($endDate)) === (24 * 3600) - 60)
				{
					$formatedEndDate = Date::parse(Config::get('dateFormat'), $objEvent->registrationEndDate);
				}
				else
				{
					$formatedEndDate = Date::parse(Config::get('datimFormat'), $objEvent->registrationEndDate);
				}
				$value = $formatedEndDate;
				break;
			case 'eventState':
				$value = static::getEventState($objEvent);
				break;
			case 'eventStateLabel':
				$value = $GLOBALS['TL_LANG']['CTE']['calendar_events'][static::getEventState($objEvent)] != '' ? $GLOBALS['TL_LANG']['CTE']['calendar_events'][static::getEventState($objEvent)] : static::getEventState($objEvent);
				break;
			case 'isLastMinuteTour':
				$value = $objEvent->eventType === 'lastMinuteTour' ? true : false;
				break;
			case 'isTour':
				$value = $objEvent->eventType === 'tour' ? true : false;
				break;
			case 'isGeneralEvent':
				$value = $objEvent->eventType === 'generalEvent' ? true : false;
				break;
			case 'isCourse':
				$value = $objEvent->eventType === 'course' ? true : false;
				break;
			case 'bookingCounter':
				$value = static::getBookingCounter($objEvent);
				break;
			case 'tourTechDifficulties':
				$value = implode(' ', static::getTourTechDifficultiesAsArray($objEvent, true));
				break;
			case 'instructors':
				$value = implode(', ', static::getInstructorNamesAsArray($objEvent, false, true));
				break;
			case 'journey':
				$value = CalendarEventsJourneyModel::findByPk($objEvent->journey) !== null ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : '';
				break;
			case 'instructorsWithQualification':
				$value = implode(', ', static::getInstructorNamesAsArray($objEvent, true, true));
				break;
			case 'courseTypeLevel1':
				$value = $objEvent->courseTypeLevel1;
				break;
			case 'eventImagePath':
				$value = static::getEventImagePath($objEvent);
				break;
			case 'eventImage':
				if (isset($arrProperty[1]))
				{
					$pictureSize = $arrProperty[1];
					$src = static::getEventImagePath($objEvent);
					$value = Controller::replaceInsertTags(sprintf('{{picture::%s?size=%s}}', $src, $pictureSize));
				}
				break;
			case 'courseTypeLevel0Name':
				$value = CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name;
				break;
			case 'courseTypeLevel1Name':
				$value = CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name;
				break;
			case 'eventOrganizerLogos':
				$value = implode('', static::getEventOrganizersLogoAsHtml($objEvent, '{{image::%s?width=60}}', false));
				break;
			case 'eventOrganizers':
				$value = implode('<br>', static::getEventOrganizersAsArray($objEvent, 'title'));
				break;
			case 'mainInstructorContactDataFromDb':
				$value = static::generateMainInstructorContactDataFromDb($objEvent);
				break;
			case 'instructorContactBoxes':
				$value = static::generateInstructorContactBoxes($objEvent);
				break;
			case 'arrTourProfile':
				$value = static::getTourProfileAsArray($objEvent);
				break;
			case 'gallery':
				$value = static::getGallery(array(
					'multiSRC'   => $objEvent->multiSRC,
					'orderSRC'   => $objEvent->orderSRC,
					'sortBy'     => 'custom',
					'perRow'     => 4,
					'size'       => serialize(array(400, 400, 'center_center', 'proportional')),
					'fullsize'   => true,
					'galleryTpl' => 'gallery_bootstrap_col-4'
				));
				break;

			default:
				$arrEvent = $objEvent->row();

				if ($objTemplate !== null && isset($objTemplate->{$strProperty}))
				{
					$value = $objTemplate->{$strProperty};
				}
				elseif (isset($arrEvent[$strProperty]))
				{
					$value = $arrEvent[$strProperty];
				}
				else
				{
					$value = "";
				}
		}

		return $value;
	}

	/**
	 * Usage in event detail reader&listing template
	 * @param  FrontendTemplate $objTemplate
	 * @throws \Exception
	 */
	public static function addEventDataToTemplate(FrontendTemplate $objTemplate)
	{
		$objEvent = CalendarEventsModel::findByPk($objTemplate->id);

		if ($objEvent !== null)
		{
			$objTemplate->getEventData = (static function ($prop) use (&$objEvent, &$objTemplate)
			{
				return static::getEventData($objEvent, $prop, $objTemplate);
			});
		}
	}

	/**
	 * Usage in static::addEventDataToTemplate()/event detail reader template
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 */
	public static function generateInstructorContactBoxes(CalendarEventsModel $objEvent): string
	{
		$strHtml = '';

		if ($objEvent !== null)
		{
			$arrInstructors = static::getInstructorsAsArray($objEvent, true);

			foreach ($arrInstructors as $userId)
			{
				$strHtml .= '<div class="mb-4 col-6 col-sm-4 col-md-6 col-xl-4"><div class="">';

				$objUser = UserModel::findByPk($userId);

				if ($objUser !== null)
				{
					$objPictureTpl = new FrontendTemplate('picture_default');
					$objPictureTpl->setData(generateAvatar($userId, 18));
					$strHtml .= '<div class="image_container portrait">';
					$strHtml .= sprintf('<a href="%s?username=%s" title="Leiter Portrait ansehen">', Controller::replaceInsertTags('{{link_url::leiter-portrait}}'), UserModel::findByPk($userId)->username);
					$strHtml .= sprintf('<figure class="avatar-large">%s</figure>', $objPictureTpl->parse());
					$strHtml .= '</a></div>';
					// End image

					// Start instructor name
					$strHtml .= '<div class="instructor-name">';
					$strQuali = '';

					if (static::getMainQualification($objUser) != '')
					{
						$strQuali .= ' (' . static::getMainQualification($objUser) . ')';
					}

					if (!$objUser->hideInFrontendListings)
					{
						$strHtml .= sprintf('<a href="%s?username=%s" title="Leiter Portrait ansehen">', Controller::replaceInsertTags('{{link_url::leiter-portrait}}'), $objUser->username);
					}

					$strHtml .= sprintf('%s %s%s', $objUser->lastname, $objUser->firstname, $strQuali);

					if (!$objUser->hideInFrontendListings)
					{
						$strHtml .= '</a>';
					}

					if (FE_USER_LOGGED_IN && !$objUser->hideInFrontendListings)
					{
						$arrContact = array('phone', 'mobile', 'email');

						foreach ($arrContact as $field)
						{
							if ($objUser->{$field} !== '')
							{
								$strHtml .= sprintf('<div class="ce_user_portrait_%s">', $field);
								$strHtml .= sprintf('<small title="%s">%s</small>', $objUser->{$field}, $objUser->{$field});
								$strHtml .= '</div>';
							}
						}
					}
					$strHtml .= '</div>';
					// End instructor name
				}
				$strHtml .= '</div></div>';
			}
		}

		return $strHtml;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 * @throws \Exception
	 */
	public static function getEventState(CalendarEventsModel $objEvent): string
	{
		if ($objEvent === null)
		{
			throw new \Exception(sprintf('Calendar Event with ID %s not found.', $objEvent->id));
		}

		$objDb = Database::getInstance();
		$objEventsMember = $objDb->prepare('SELECT COUNT(id) AS registrationCount FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=?')->execute($objEvent->id, 'subscription-accepted');
		$registrationCount = $objEventsMember->registrationCount;

		// Event canceled
		if ($objEvent->eventState === 'event_canceled')
		{
			return 'event_status_4';
		}

		// Event deferred
		if ($objEvent->eventState === 'event_deferred')
		{
			return 'event_status_6';
		}

		// Event is fully booked
		if ($objEvent->eventState === 'event_fully_booked' || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers))
		{
			return 'event_status_3'; // fa-circle red
		}

		// Event is over or booking is no more possible
		if ($objEvent->startDate <= time() || ($objEvent->setRegistrationPeriod && $objEvent->registrationEndDate < time()))
		{
			return 'event_status_2';
		}

		// Booking not possible yet
		if ($objEvent->setRegistrationPeriod && $objEvent->registrationStartDate > time())
		{
			return 'event_status_5'; // fa-circle orange
		}

		// If online registration is disabeld in the event settings
		if ($objEvent->disableOnlineRegistration)
		{
			return 'event_status_7';
		}

		return 'event_status_1';
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return bool
	 */
	public static function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
	{
		if ($objEvent !== null)
		{
			$objEventsMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=?')->execute($objEvent->id, 'subscription-accepted');
			$registrationCount = $objEventsMember->numRows;

			if ($objEvent->eventState === 'event_fully_booked' || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 */
	public static function getMainInstructorName(CalendarEventsModel $objEvent): string
	{
		$strName = '';

		if ($objEvent !== null)
		{
			$objDb = Database::getInstance();
			$objInstructor = $objDb->prepare('SELECT * FROM tl_calendar_events_instructor WHERE pid=? AND isMainInstructor=?')->limit(1)->execute($objEvent->id, '1');

			if ($objInstructor->numRows)
			{
				$objUser = UserModel::findByPk($objInstructor->id);

				if ($objUser !== null)
				{
					$arrName = array();
					$arrName[] = $objUser->lastname;
					$arrName[] = $objUser->firstname;
					$arrName = array_filter($arrName);
					$strName = implode(' ', $arrName);
				}
			}
		}

		return $strName;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 */
	public static function generateMainInstructorContactDataFromDb(CalendarEventsModel $objEvent): string
	{
		if ($objEvent !== null)
		{
			$arrInstructors = static::getInstructorsAsArray($objEvent, false);
			$objUser = UserModel::findByPk($arrInstructors[0]);

			if ($objUser !== null)
			{
				$arrContact = array();
				$arrContact[] = sprintf('<strong>%s %s</strong>', $objUser->lastname, $objUser->firstname);
				$arrContact[] = sprintf('Tel.: %s', $objUser->phone);
				$arrContact[] = sprintf('Mobile: %s', $objUser->mobile);
				$arrContact[] = sprintf('E-Mail: %s', $objUser->email);
				$arrContact = array_filter($arrContact);

				return implode(', ', $arrContact);
			}
		}

		return '';
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  bool                $blnShowPublishedOnly
	 * @return array
	 */
	public static function getInstructorsAsArray(CalendarEventsModel $objEvent, $blnShowPublishedOnly = true): array
	{
		$arrInstructors = array();

		if ($objEvent !== null)
		{
			// Get all instructors from an event, list mainInstructor first
			$objDb = Database::getInstance();
			$objInstructor = $objDb->prepare('SELECT * FROM tl_calendar_events_instructor WHERE pid=? ORDER BY isMainInstructor DESC')->execute($objEvent->id);

			while ($objInstructor->next())
			{
				$objUser = UserModel::findByPk($objInstructor->userId);

				if ($objUser !== null)
				{
					if ($blnShowPublishedOnly === true && $objUser->disable)
					{
						continue;
					}
					$arrInstructors[] = $objUser->id;
				}
			}
		}

		return $arrInstructors;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  false               $blnAddMainQualification
	 * @param  bool                $blnShowPublishedOnly
	 * @return array
	 */
	public static function getInstructorNamesAsArray(CalendarEventsModel $objEvent, $blnAddMainQualification = false, $blnShowPublishedOnly = true): array
	{
		$arrInstructors = array();

		if ($objEvent !== null)
		{
			$arrUsers = static::getInstructorsAsArray($objEvent, $blnShowPublishedOnly);

			foreach ($arrUsers as $userId)
			{
				$objUser = UserModel::findByPk($userId);

				if ($objUser !== null)
				{
					if ($blnShowPublishedOnly === true && $objUser->disable)
					{
						continue;
					}

					$strName = trim($objUser->lastname . ' ' . $objUser->firstname);

					if ($blnAddMainQualification && static::getMainQualification($objUser) != '')
					{
						$arrInstructors[] = $strName . ' (' . static::getMainQualification($objUser) . ')';
					}
					else
					{
						$arrInstructors[] = $strName;
					}
				}
			}
		}

		return $arrInstructors;
	}

	/**
	 * @param  UserModel $objUser
	 * @return string
	 */
	public static function getMainQualification(UserModel $objUser): string
	{
		$strQuali = '';

		if ($objUser !== null)
		{
			$arrQuali = StringUtil::deserialize($objUser->leiterQualifikation, true);

			if (!empty($arrQuali[0]))
			{
				$strQuali = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'][$arrQuali[0]];
			}
		}

		return $strQuali;
	}

	/**
	 * @param $arrData
	 * @return string
	 */
	public static function getGallery(array $arrData): string
	{
		$arrData['type'] = 'gallery';

		if (!isset($arrData['perRow']) || $arrData['perRow'] < 1)
		{
			$arrData['perRow'] = 1;
		}

		$objModel = new ContentModel();
		$objModel->setRow($arrData);

		$objGallery = new ContentGallery($objModel);
		$strBuffer = $objGallery->generate();

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['getContentElement']) && \is_array($GLOBALS['TL_HOOKS']['getContentElement']))
		{
			foreach ($GLOBALS['TL_HOOKS']['getContentElement'] as $callback)
			{
				$strBuffer = System::importStatic($callback[0])->{$callback[1]}($objModel, $strBuffer, $objGallery);
			}
		}

		return $strBuffer;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 */
	public static function getEventImagePath(CalendarEventsModel $objEvent): string
	{
		// Get root dir
		$rootDir = System::getContainer()->getParameter('kernel.project_dir');
		System::getContainer()->get('contao.framework')->initialize();

		if ($objEvent !== null)
		{
			if ($objEvent->singleSRC != '')
			{
				$objFile = FilesModel::findByUuid($objEvent->singleSRC);

				if ($objFile !== null && is_file($rootDir . '/' . $objFile->path))
				{
					return $objFile->path;
				}
			}
		}

		return Config::get('SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC');
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  string              $dateFormat
	 * @param  bool                $blnAppendEventDuration
	 * @param  bool                $blnTooltip
	 * @param  bool                $blnInline
	 * @return string
	 * @throws \Exception
	 */
	public static function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = '', bool $blnAppendEventDuration = true, bool $blnTooltip = true, bool $blnInline = false)
	{
		if ($objEvent === null)
		{
			return '';
		}

		if (empty($dateFormat))
		{
			$dateFormat = Config::get('dateFormat');
		}

		$dateFormatShortened = $dateFormat;

		if ($dateFormat === 'd.m.Y')
		{
			$dateFormatShortened = 'd.m.';
		}

		$eventDuration = \count(self::getEventTimestamps($objEvent));
		$span = (int) Calendar::calculateSpan(self::getStartDate($objEvent), self::getEndDate($objEvent)) + 1;

		if ($eventDuration === 1)
		{
			return Date::parse($dateFormat, self::getStartDate($objEvent)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($objEvent) . ')' : '');
		}

		if ($span === $eventDuration)
		{
			// von bis
			return Date::parse($dateFormatShortened, self::getStartDate($objEvent)) . ' - ' . Date::parse($dateFormat, self::getEndDate($objEvent)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($objEvent) . ')' : '');
		}

		$arrDates = array();
		$dates = self::getEventTimestamps($objEvent);

		foreach ($dates as $date)
		{
			$arrDates[] = Date::parse($dateFormat, $date);
		}

		if ($blnTooltip)
		{
			return Date::parse($dateFormat, self::getStartDate($objEvent)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($objEvent) . ')' : '') . (!$blnInline ? '<br>' : ' ') . '<a tabindex="0" class="more-date-infos" data-toggle="tooltip" data-placement="bottom" title="Eventdaten: ' . implode(', ', $arrDates) . '">und weitere</a>';
		}

		$dateString = '';

		foreach (self::getEventTimestamps($objEvent) as $tstamp)
		{
			$dateString .= sprintf('<time datetime="%s">%s</time>', Date::parse('Y-m-d', $tstamp), Date::parse('D, d.m.Y', $tstamp));
		}
		$dateString .= $blnAppendEventDuration ? sprintf('<time>(%s)</time>', self::getEventDuration($objEvent)) : '';

		return $dateString;
	}

	/**
	 * @param $id
	 * @param  string $dateFormatStart
	 * @param  string $dateFormatEnd
	 * @return string
	 */
	public static function getBookingPeriod($id, $dateFormatStart = '', $dateFormatEnd = '')
	{
		$objEvent = CalendarEventsModel::findByPk($id);

		if ($objEvent === null)
		{
			return '';
		}

		if (!$objEvent->setRegistrationPeriod)
		{
			return '';
		}

		if ($dateFormatStart === '')
		{
			$dateFormatStart = Config::get('dateFormat');
		}

		if ($dateFormatEnd === '')
		{
			$dateFormatEnd = Config::get('dateFormat');
		}

		return Date::parse($dateFormatStart, $objEvent->registrationStartDate) . ' - ' . Date::parse($dateFormatEnd, $objEvent->registrationEndDate);
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return array|bool
	 */
	public static function getEventTimestamps(CalendarEventsModel $objEvent)
	{
		$arrRepeats = array();

		if ($objEvent !== null)
		{
			$arrDates = StringUtil::deserialize($objEvent->eventDates);

			if (!\is_array($arrDates) || empty($arrDates))
			{
				return false;
			}

			foreach ($arrDates as $v)
			{
				$arrRepeats[] = $v['new_repeat'];
			}
		}

		return $arrRepeats;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return int
	 */
	public static function getStartDate(CalendarEventsModel $objEvent): int
	{
		$tstamp = 0;

		if ($objEvent !== null)
		{
			$arrDates = StringUtil::deserialize($objEvent->eventDates);

			if (!\is_array($arrDates) || empty($arrDates))
			{
				return $tstamp;
			}
			$tstamp = (int) $arrDates[0]['new_repeat'];
		}

		return $tstamp;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return int
	 */
	public static function getEndDate(CalendarEventsModel $objEvent): int
	{
		$tstamp = 0;

		if ($objEvent !== null)
		{
			$arrDates = StringUtil::deserialize($objEvent->eventDates);

			if (!\is_array($arrDates) || empty($arrDates))
			{
				return $tstamp;
			}
			$tstamp = (int) $arrDates[\count($arrDates) - 1]['new_repeat'];
		}

		return $tstamp;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 * @throws \Exception
	 */
	public static function getEventDuration(CalendarEventsModel $objEvent): string
	{
		if ($objEvent === null)
		{
			throw new \Exception(sprintf('Calendar Event with ID %s not found.', $objEvent->id));
		}

		$arrDates = StringUtil::deserialize($objEvent->eventDates);

		if ($objEvent->durationInfo != '')
		{
			return (string) $objEvent->durationInfo;
		}

		if (!empty($arrDates) && \is_array($arrDates))
		{
			return sprintf('%s Tage', \count($arrDates));
		}

		return '';
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  bool                $tooltip
	 * @return array
	 */
	public static function getTourTechDifficultiesAsArray(CalendarEventsModel $objEvent, $tooltip = false): array
	{
		$arrReturn = array();

		if ($objEvent !== null)
		{
			$arrValues = StringUtil::deserialize($objEvent->tourTechDifficulty, true);

			if (!empty($arrValues) && \is_array($arrValues))
			{
				$arrDiff = array();

				foreach ($arrValues as $difficulty)
				{
					$strDiff = '';
					$strDiffTitle = '';

					if (\strlen($difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax']))
					{
						$objDiff = TourDifficultyModel::findByPk((int) ($difficulty['tourTechDifficultyMin']));

						if ($objDiff !== null)
						{
							$strDiff = $objDiff->shortcut;
							$strDiffTitle = $objDiff->title;
						}
						$objDiff = TourDifficultyModel::findByPk((int) ($difficulty['tourTechDifficultyMax']));

						if ($objDiff !== null)
						{
							$max = $objDiff->shortcut;
							$strDiff .= ' - ' . $max;
							$strDiffTitle .= ' - ' . $objDiff->title;
						}
					}
					elseif (\strlen($difficulty['tourTechDifficultyMin']))
					{
						$objDiff = TourDifficultyModel::findByPk((int) ($difficulty['tourTechDifficultyMin']));

						if ($objDiff !== null)
						{
							$strDiff = $objDiff->shortcut;
							$strDiffTitle = $objDiff->title;
						}
						$arrDiff[] = $strDiff;
					}

					if ($strDiff !== '')
					{
						if ($tooltip)
						{
							$html = '<span class="badge badge-pill bg-primary" data-toggle="tooltip" data-placement="top" title="Techn. Schwierigkeit: %s">%s</span>';
							$arrReturn[] = sprintf($html, $strDiffTitle, $strDiff);
						}
						else
						{
							$arrReturn[] = $strDiff;
						}
					}
				}
			}
		}

		return $arrReturn;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  string              $field
	 * @param  bool                $tooltip
	 * @return array
	 */
	public static function getTourTypesAsArray(CalendarEventsModel $objEvent, $field = 'shortcut', $tooltip = false): array
	{
		$arrReturn = array();

		if ($objEvent !== null)
		{
			$arrValues = StringUtil::deserialize($objEvent->tourType, true);

			if (!empty($arrValues) && \is_array($arrValues))
			{
				foreach ($arrValues as $id)
				{
					$objModel = TourTypeModel::findByPk($id);

					if ($objModel !== null)
					{
						if ($tooltip)
						{
							$html = '<span class="badge badge-pill bg-secondary" data-toggle="tooltip" data-placement="top" title="Typ: %s">%s</span>';
							$arrReturn[] = sprintf($html, $objModel->{'title'}, $objModel->{$field});
						}
						else
						{
							$arrReturn[] = $objModel->{$field};
						}
					}
				}
			}
		}

		return $arrReturn;
	}

	/**
	 * Return a bootstrap badge with some booking count information
	 * @param  CalendarEventsModel $objEvent
	 * @return string
	 */
	public static function getBookingCounter(CalendarEventsModel $objEvent): string
	{
		$strBadge = '<span class="badge badge-pill bg-%s" data-toggle="tooltip" data-placement="top" title="%s">%s</span>';

		if ($objEvent !== null)
		{
			$objDb = Database::getInstance();
			$calendarEventsMember = $objDb->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? && stateOfSubscription=?')->execute($objEvent->id, 'subscription-accepted');
			$memberCount = $calendarEventsMember->numRows;

			if ($objEvent->eventState === 'event_canceled')
			{
				// Event canceled
				return '';
			}

			if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0)
			{
				if ($memberCount >= $objEvent->maxMembers)
				{
					// Event fully booked
					return sprintf($strBadge, 'danger', 'ausgebucht', $memberCount . '/' . $objEvent->maxMembers);
				}

				if ($memberCount < $objEvent->maxMembers)
				{
					// Free places
					return sprintf($strBadge, 'success', sprintf('noch %s freie Pl&auml;tze', $objEvent->maxMembers - $memberCount), $memberCount . '/' . $objEvent->maxMembers);
				}
			}
			else
			{
				// There is no booking limit. Show registered members
				return sprintf($strBadge, 'success', $memberCount . ' Anmeldungen', $memberCount . '/?');
			}
		}

		return '';
	}

	/**
	 * @param $objEvent
	 * @return string
	 */
	public function getEventStateOfSubscriptionBadgesString($objEvent)
	{
		$strRegistrationsBadges = '';
		$intNotConfirmed = 0;
		$intAccepted = 0;
		$intRefused = 0;
		$intWaitlisted = 0;
		$intUnsubscribedUser = 0;

		$eventsMemberModel = CalendarEventsMemberModel::findByEventId($objEvent->id);

		if ($eventsMemberModel !== null)
		{
			while ($eventsMemberModel->next())
			{
				if ($eventsMemberModel->stateOfSubscription === 'subscription-not-confirmed')
				{
					$intNotConfirmed++;
				}

				if ($eventsMemberModel->stateOfSubscription === 'subscription-accepted')
				{
					$intAccepted++;
				}

				if ($eventsMemberModel->stateOfSubscription === 'subscription-refused')
				{
					$intRefused++;
				}

				if ($eventsMemberModel->stateOfSubscription === 'subscription-waitlisted')
				{
					$intWaitlisted++;
				}

				if ($eventsMemberModel->stateOfSubscription === 'user-has-unsubscribed')
				{
					$intUnsubscribedUser++;
				}
			}
			$refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

			$href = sprintf("'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s'", $objEvent->id, REQUEST_TOKEN, $refererId);

			if ($intNotConfirmed > 0)
			{
				$strRegistrationsBadges .= sprintf('<span class="subscription-badge not-confirmed blink" title="%s unbestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intNotConfirmed, $href, $intNotConfirmed);
			}

			if ($intAccepted > 0)
			{
				$strRegistrationsBadges .= sprintf('<span class="subscription-badge accepted" title="%s bestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intAccepted, $href, $intAccepted);
			}

			if ($intRefused > 0)
			{
				$strRegistrationsBadges .= sprintf('<span class="subscription-badge refused" title="%s abgelehnte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intRefused, $href, $intRefused);
			}

			if ($intWaitlisted > 0)
			{
				$strRegistrationsBadges .= sprintf('<span class="subscription-badge waitlisted" title="%s Anmeldungen auf Warteliste" role="button" onclick="window.location.href=%s">%s</span>', $intWaitlisted, $href, $intWaitlisted);
			}

			if ($intUnsubscribedUser > 0)
			{
				$strRegistrationsBadges .= sprintf('<span class="subscription-badge unsubscribed-user" title="%s Abgemeldete Teilnehmer" role="button" onclick="window.location.href=%s">%s</span>', $intUnsubscribedUser, $href, $intUnsubscribedUser);
			}
		}

		return $strRegistrationsBadges;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  string              $field
	 * @return array
	 */
	public static function getEventOrganizersAsArray(CalendarEventsModel $objEvent, $field = 'title'): array
	{
		$arrReturn = array();

		if ($objEvent !== null)
		{
			$arrValues = StringUtil::deserialize($objEvent->organizers, true);

			if (!empty($arrValues) && \is_array($arrValues))
			{
				foreach ($arrValues as $id)
				{
					$objModel = EventOrganizerModel::findByPk($id);

					if ($objModel !== null)
					{
						$arrReturn[] = $objModel->{$field};
					}
				}
			}
		}

		return $arrReturn;
	}

	/**
	 * Check if event dates are not already occupied by an other booked event
	 * @param  CalendarEventsModel $objEvent
	 * @param  MemberModel         $objMember
	 * @return bool
	 */
	public static function areBookingDatesOccupied(CalendarEventsModel $objEvent, MemberModel $objMember): bool
	{
		if ($objEvent === null || $objMember === null)
		{
			return true;
		}

		$arrEventDates = array();
		$arrEventRepeats = StringUtil::deserialize($objEvent->eventDates, true);

		if (!empty($arrEventRepeats) && \is_array($arrEventRepeats))
		{
			foreach ($arrEventRepeats as $eventRepeat)
			{
				if (isset($eventRepeat['new_repeat']) && !empty($eventRepeat['new_repeat']))
				{
					$arrEventDates[] = $eventRepeat['new_repeat'];
				}
			}
		}

		// Get all future events of the member
		$objMemberEvents = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId!=? AND contaoMemberId=? AND stateOfSubscription=? AND hasParticipated=?')
			->execute($objEvent->id, $objMember->id, 'subscription-accepted', '');

		while ($objMemberEvents->next())
		{
			$objMemberEvent = CalendarEventsModel::findByPk($objMemberEvents->eventId);

			if ($objMemberEvent !== null)
			{
				$arrRepeats = StringUtil::deserialize($objMemberEvent->eventDates, true);

				if (!empty($arrRepeats) && \is_array($arrRepeats))
				{
					foreach ($arrRepeats as $repeat)
					{
						if (isset($repeat['new_repeat']) && !empty($repeat['new_repeat']))
						{
							if (\in_array($repeat['new_repeat'], $arrEventDates, false))
							{
								// This date is already occupied (do not allow booking)
								return true;
							}
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param $objEvent
	 * @return string|string[]|null
	 */
	public static function generateEventPreviewUrl($objEvent)
	{
		$strUrl = '';

		if ($objEvent->eventType != '')
		{
			$objEventType = EventTypeModel::findByAlias($objEvent->eventType);

			if ($objEventType !== null)
			{
				if ($objEventType->previewPage > 0)
				{
					$objPage = PageModel::findByPk($objEventType->previewPage);

					if ($objPage instanceof PageModel)
					{
						$params = (Config::get('useAutoItem') ? '/' : '/events/') . ($objEvent->alias ?: $objEvent->id);
						$strUrl = ampersand($objPage->getFrontendUrl($params));
						$strUrl = Url::addQueryString('mode=eventPreview', $strUrl);
						$strUrl = Url::addQueryString('eventToken=' . $objEvent->eventToken, $strUrl);
					}
				}
			}
		}

		return $strUrl;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @return array
	 */
	public static function getTourProfileAsArray(CalendarEventsModel $objEvent): array
	{
		$arrProfile = array();

		if ($objEvent !== null)
		{
			if (!empty($objEvent->tourProfile) && \is_array(StringUtil::deserialize($objEvent->tourProfile)))
			{
				$m = 0;
				$arrTourProfile = StringUtil::deserialize($objEvent->tourProfile, true);

				foreach ($arrTourProfile as $profile)
				{
					if (empty($profile['tourProfileAscentMeters']) && empty($profile['tourProfileAscentTime']) && empty($profile['tourProfileDescentMeters']) && empty($profile['tourProfileDescentTime']))
					{
						continue;
					}
					$m++;

					$arrAsc = array();
					$arrDesc = array();

					if (\count($arrTourProfile) > 1)
					{
						$strProfile = sprintf('%s. Tag: ', $m);
					}
					else
					{
						$strProfile = '';
					}

					if ($profile['tourProfileAscentMeters'] != '')
					{
						$arrAsc[] = sprintf('%s Hm', $profile['tourProfileAscentMeters']);
					}

					if ($profile['tourProfileAscentTime'] != '')
					{
						$arrAsc[] = sprintf('%s h', $profile['tourProfileAscentTime']);
					}

					if ($profile['tourProfileDescentMeters'] != '')
					{
						$arrDesc[] = sprintf('%s Hm', $profile['tourProfileDescentMeters']);
					}

					if ($profile['tourProfileDescentTime'] != '')
					{
						$arrDesc[] = sprintf('%s h', $profile['tourProfileDescentTime']);
					}

					if (\count($arrAsc) > 0)
					{
						$strProfile .= 'Aufst: ' . implode('/', $arrAsc);
					}

					if (\count($arrDesc) > 0)
					{
						$strProfile .= ($strProfile != '' ? ', ' : '') . 'Abst: ' . implode('/', $arrDesc);
					}

					$arrProfile[] = $strProfile;
				}
			}
		}

		return $arrProfile;
	}

	/**
	 * @param $field
	 * @param $value
	 * @param $strTable
	 * @param $dataRecord
	 * @param $dca
	 * @return mixed|string|string[]|null
	 */
	public function exportRegistrationListHook($field, $value, $strTable, $dataRecord, $dca)
	{
		if ($strTable === 'tl_calendar_events_member')
		{
			if ($field === 'dateOfBirth' || $field === 'addedOn')
			{
				if ((int) $value)
				{
					$value = Date::parse('Y-m-d', $value);
				}
			}

			if ($field === 'phone' || $field === 'phone')
			{
				$value = str_replace(' ', '', (string) $value);

				if (\strlen((string) $value) === 10)
				{
					// Format phone numbers to 0xx xxx xx xx
					$value = preg_replace('/^0(\d{2})(\d{3})(\d{2})(\d{2})/', '0${1} ${2} ${3} ${4}', $value, -1, $count);
				}
			}

			if ($field === 'stateOfSubscription')
			{
				Controller::loadLanguageFile('tl_calendar_events_member');

				if (\strlen($value) && isset($GLOBALS['TL_LANG']['tl_calendar_events_member'][$value]))
				{
					$value = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value];
				}
			}
		}

		return $value;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  string              $strInsertTag
	 * @param  false               $allowDuplicate
	 * @return array
	 */
	public function getEventOrganizersLogoAsHtml(CalendarEventsModel $objEvent, $strInsertTag = '{{image::%s}}', $allowDuplicate = false): array
	{
		$arrHtml = array();
		$arrUuids = array();

		if ($objEvent !== null)
		{
			$arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);

			foreach ($arrOrganizers as $orgId)
			{
				$objOrganizer = EventOrganizerModel::findByPk($orgId);

				if ($objOrganizer !== null)
				{
					if ($objOrganizer->addLogo && $objOrganizer->singleSRC != '')
					{
						if (\in_array($objOrganizer->singleSRC, $arrUuids, false) && !$allowDuplicate)
						{
							continue;
						}
						$arrUuids[] = $objOrganizer->singleSRC;
						$strLogo = Controller::replaceInsertTags(sprintf($strInsertTag, StringUtil::binToUuid($objOrganizer->singleSRC)));

						if ($strLogo != '')
						{
							$arrHtml[] = $strLogo;
						}
					}
				}
			}
		}

		return $arrHtml;
	}

	/**
	 * @param  CalendarEventsModel $objEvent
	 * @param  array               $arrOptions
	 * @param  bool                $blnAbsoluteUrl
	 * @param  bool                $blnCache
	 * @return string|null
	 */
	public static function getEventQrCode(CalendarEventsModel $objEvent, array $arrOptions = array(), bool $blnAbsoluteUrl = true, bool $blnCache = true)
	{
		if ($objEvent !== null)
		{
			// Generate QR code folder
			$objFolder = new Folder('system/qrcodes');

			// Symlink
			$rootDir = System::getContainer()->getParameter('kernel.project_dir');
			SymlinkUtil::symlink($objFolder->path, 'web/' . $objFolder->path, $rootDir);

			// Generate path
			$filepath = sprintf($objFolder->path . '/' . 'eventQRcode_%s.png', $objEvent->id);

			// Defaults
			$opt = array(
				'version'    => 5,
				'scale'      => 4,
				'outputType' => QRCode::OUTPUT_IMAGE_PNG,
				'eccLevel'   => QRCode::ECC_L,
				'cachefile'  => $filepath
			);

			if (!$blnCache && isset($opt['cachefile']))
			{
				unset($opt['cachefile']);
			}

			$options = new QROptions(array_merge($opt, $arrOptions));

			// Get event reader url
			$url = Events::generateEventUrl($objEvent, $blnAbsoluteUrl);

			// Generate QR and return the image path
			if ((new QRCode($options))->render($url, $filepath))
			{
				return $filepath;
			}
		}

		return null;
	}

	public static function getSectionMembershipAsString(MemberModel $objMember): string
	{
		Controller::loadLanguageFile('tl_member');
		$arrSections = array();
		$sections = StringUtil::deserialize($objMember->sectionId, true);

		foreach ($sections as $id)
		{
			$arrSections[] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?: $id;
		}

		return implode(', ', $arrSections);
	}
}
