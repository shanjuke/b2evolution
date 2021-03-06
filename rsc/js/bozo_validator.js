/**
 * "BOZO VALIDATOR 2" : Check if a form has been changed but not submitted when a bozo clicks
 * on a link which will result in potential data input loss
 * Tested on Firefox (XP & Mac OSX) , IE6 (XP), Safari (Mac OSX)
 * b2evolution - http://b2evolution.net/
 * @version $Id: bozo_validator.js 7905 2014-12-26 07:53:17Z yura $
 */
var bozo_confirm_mess;

var bozo = {

	// array of changes for each form we need to verify (needed to detect if another form has changed when we submit)
	'tab_changes' : Object(),
	// Total number of changes
	'nb_changes' : 0,

	// If no translated message has been provided, use this default:
	'confirm_mess' : bozo_confirm_mess ? bozo_confirm_mess : 'You have modified this form but you haven\'t submitted it yet.\nYou are about to lose your edits.\nAre you sure?',

	/**
	 *	BOZO VALIDATOR INITIALIZATION
	 * This is designed to track changes on forms whith an ID including '_checkchanges'
	 */
	init: function ( )
	{
		// Loop through all forms:
		var inputs = jQuery("form")
			// Initialize this form as having no changes yet:
			.each( function() { bozo.tab_changes[this.id] = 0; } )
			// add submit event on the form to control if there are changes on others forms:
			.submit(bozo.validate_submit)
			// Filter all "*_checkchanges" forms:
			.filter("[id$='_checkchanges']")
			// Hook "click" event for reset elements:
			.find("input[type=reset]:not([class$=_nocheckchanges])").click(bozo.reset_changes).end()
			// Hook "change" and "keypress" event for all others:
			.find("input[type=text], input[type=password], input[type=radio], input[type=checkbox], input[type=file], input[type=hidden], textarea")
				.not("[class$=_nocheckchanges]");
			inputs.each( function()
			{
				if( jQuery( this ).hasClass( 'form_color_input' ) )
				{	// Store initialized color in order to know when we should skip a counting of changed inputs:
					jQuery( this ).data( 'bozo-init-color', jQuery( this ).val() );
				}
			} );
			inputs.on("change", bozo.change)
				.on("keypress", bozo.change);
	},


	/**
	 *	caters for the differences between Internet Explorer and fully DOM-supporting browsers
	 */
	findTarget: function ( e )
	{
		var target;
		if (window.event && window.event.srcElement)
			target = window.event.srcElement;
		else if (e && e.target)
			target = e.target;

		if (!target)
			return null;

		return target;
	},


	/*
	 * called when there is a change event on an element
	 */
	change: function( e )
	{	// Get the target element
		var target = bozo.findTarget( e );

		if( typeof( jQuery( target ).data( 'bozo-init-color' ) ) != 'undefined' )
		{	// Check if color input is really changed:
			var init_color_val = jQuery( target ).data( 'bozo-init-color' ).toLowerCase();
			var curr_color_val = jQuery( target ).val().toLowerCase();
			var check_same_color = init_color_val.substr( 1, 3 );
			if( init_color_val == curr_color_val ||
			    ( ( init_color_val == '#' + check_same_color || init_color_val == '#' + check_same_color + check_same_color ) &&
			      ( curr_color_val == '#' + check_same_color || curr_color_val == '#' + check_same_color + check_same_color ) ) )
			{	// Skip autofixing action of colorpicker on initialization to avoid false alerts about changed inputs,
				// e.g. colorpicker autofix color values on initialization from #333 to #333333 or from #F0F0F0 to #f0f0f0 and etc.
				return;
			}
		}

		// Update changes number for his parent form
		bozo.tab_changes[ jQuery( target ).closest( 'form' ).attr( 'id' ) ]++;
		// Update Total changes number
		bozo.nb_changes++;
	},


	/*
	 * Call when there is a click on a reset input
	 * Reset changes
	 */
	reset_changes: function ( e )
	{
		// Loop on the forms changes array
		for( i in bozo.tab_changes )
		{	// Reset changes number to 0
			bozo.tab_changes[i] = 0;
		}
		// Total changes number
		bozo.nb_changes = 0;
	},


	/*
	 *	Called when there is a click event on a submit button
	 *	If there are no changes on others forms, cancel onbeforeunload event
	 */
	validate_submit: function( e )
	{
			var target = bozo.findTarget(e);

			var other_form_changes = 0;

			// Loop on the forms changes array
			for( i in bozo.tab_changes )
			{
				if ( ( i != jQuery( target ).closest( 'form' ).attr( 'id' ) ) && bozo.tab_changes[i] )
				{	// Another form contains input changes
					other_form_changes++;
				}
			}

			if( !other_form_changes )
			{	// There are no changes on others forms, so cancel onbeforeunload event
				jQuery( window ).off( 'beforeunload' );
				return;
			}
	},



	/**
	 * Called when the user close the window
	 * Ask confirmation to close page without saving changes if there have been changes on all form inputs
	 */
	validate_close: function( e )
	{
		if( bozo.nb_changes )
		{ // there are input changes
			if( window.event )
			{ // For ie:
				window.event.returnValue = bozo.confirm_mess;
			}
			// For all other browsers:
			return bozo.confirm_mess;
		}
	}

}

// Init Bozo validator when the window is loaded:
jQuery( document ).ready( bozo.init );
// Note: beforeunload is a "very special" event and cannot be added with addEvent:
jQuery( window ).on( 'beforeunload', bozo.validate_close );