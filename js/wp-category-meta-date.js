jQuery(document).ready(function ($) {
	if ( typeof wptm_date !== "undefined" && typeof wptm_date_ex !== "undefined" ) { 
		$.datepicker.setDefaults($.extend({
		changeMonth: wptm_date_ex.changeMonth, 
		changeYear: wptm_date_ex.changeYear,
		showOtherMonths: wptm_date_ex.showOtherMonths,
		selectOtherMonth: wptm_date_ex.selectOtherMonth, 
		showWeek: wptm_date_ex.showWeek, 
		calculateWeek: wptm_date_ex.calculateWeek,
		shortYearCutoff: wptm_date_ex.shortYearCutoff}));
		// basic settings
		jQuery('.wptm_date').datepicker({
			
		// Show the 'close' and 'today' buttons
		showButtonPanel: true,
		closeText: wptm_date.closeText,
		currentText: wptm_date.currentText,
		monthNames: wptm_date.monthNames,
		monthNamesShort: wptm_date.monthNamesShort,
		dayNames: wptm_date.dayNames,
		dayNamesShort: wptm_date.dayNamesShort,
		dayNamesMin: wptm_date.dayNamesMin,
		dateFormat: wptm_date.dateFormat,
		firstDay: wptm_date.firstDay,
		isRTL: wptm_date.isRTL,

    });}
	else jQuery('.wptm_date').datepicker();
});	 
