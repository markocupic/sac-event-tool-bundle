/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

//Provides methods for filtering the event list
var EventFilter = {

	options: null,

	/**
	 * Initialize filter board
	 * @param eventList
	 * @param options
	 */
	initialize: function (opt) {
		let self = this;

		self.options = opt;

		// Initialize Select2 for organizer input
		$('#ctrl_organizers').select2();

		if ($('#ctrl_year')) {
			window.setInterval(() => {
				if ($('.select2-selection__choice').length) {
					$('.select2-selection').css({
						'height': 'auto',
					});
				} else {
					$('.select2-selection').css({
						'height': $('#ctrl_year').outerHeight() + 'px',
					});
				}

			}, 100);
		}

		window.addEventListener('resize', function () {
			$('.select2.select2-container').css({
				'max-width': '100%',
				'width': '100%',
			});
		});

		window.setTimeout(() => {
			$('.filter-board-widget').css('visibility', 'visible');
		}, 20);

		// Reset form
		$('.filter-board .reset-form').click(function (e) {
			e.stopPropagation();
			e.preventDefault();
			window.location.href = location.href.replace(location.search, '');
		});

		//Set Datepicker
		const datePickerOpt = {
			dateFormat: self.options.dateFormat,
			"locale": self.options.locale,
		}

		const today = new Date();
		const mm = today.getMonth() + 1;
		const dd = today.getDate();
		const YYYY = today.getFullYear();

		// Set datepickers start and end date
		if (self.getUrlParam('year') > 0) {
			datePickerOpt.minDate = self.getUrlParam('year') + '-01-01';
			datePickerOpt.maxDate = self.getUrlParam('year') + '-12-31';
			datePickerOpt.defaultDate = '';

			if (self.getUrlParam('dateStart') != '') {
				datePickerOpt.defaultDate = self.getUrlParam('dateStart');
			}
		} else {
			const today = new Date();
			const mm = today.getMonth() + 1;
			const dd = today.getDate();
			let YYYY = today.getFullYear();
			datePickerOpt.minDate = YYYY + '-' + mm + '-' + dd;
			YYYY = YYYY + 2;
			datePickerOpt.maxDate = YYYY + '-' + mm + '-' + dd;
		}

		flatpickr("#ctrl_dateStart", datePickerOpt);

	},
	/**
	 * @param strParam
	 * @returns {*}
	 */
	getUrlParam: function (strParam) {
		"use strict";
		const results = new RegExp('[\?&]' + strParam + '=([^&#]*)').exec(window.location.href);
		if (results === null) {
			return 0;
		}

		return results[1] || 0;
	}
};
