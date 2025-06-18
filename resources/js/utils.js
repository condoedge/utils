function getYesNoValue(id)
{
	const group = $('#' + id);

	const rawValue = group.find('.selected').find('*[data-value]').data('value');
	
	if (rawValue == undefined) {
		return null;
	}

	const value = rawValue == 'yes' ? 1 : 0;

	return value;
}

function getToggle(name)
{
	return $('input[type=checkbox][name="' + name + '"]');
}

function getToggleValue(name)
{
	const toggleCheckbox = getToggle(name);
    const checked = toggleCheckbox.attr('aria-checked') == "true";

	return checked ? 1 : 0;
}

function toggleYesNo(name) {
	const groupId = 'temp_' + name;
	const toggleName = name + '_toggle';

	const groupValue = getYesNoValue(groupId);
	const toggleValue = getToggleValue(toggleName);

	// This want to fix the first "no" select when the default is null
	if(groupValue == null) {
		$('#' + name + '-off-input').hide();
		$('#' + name + '-on-input').hide();

		return
	}

	if (groupValue != toggleValue) {
		console.log(getToggle(toggleName));
		getToggle(toggleName).each(function(key, toggle) {
			const evt = new Event( 'click', { bubbles: true } );
			toggle.nextElementSibling.dispatchEvent(evt);
		});
	} else if (groupValue == 1) {
		$('#' + name + '-off-input').hide();
		$('#' + name + '-on-input').show();
	} else if(groupValue == 0) {
		$('#' + name + '-off-input').show();
		$('#' + name + '-on-input').hide();
	}  
}

function setLoadingScreen()
{
    const loadingInnerHtml = `
        <div class="loading-screen">
            <div class="spinner">
                
            </div>    
        </div>
    `;

    $('body').append(loadingInnerHtml);
}

function removeLoadingScreen()
{
	$('.loading-screen').remove();
}

import introJs from 'intro.js';
window.introJs = introJs;

window.utils = {
	getYesNoValue: getYesNoValue,
	getToggle: getToggle,
	getToggleValue: getToggleValue,
	toggleYesNo: toggleYesNo,
	introJs: introJs,
	setLoadingScreen: setLoadingScreen,
	removeLoadingScreen: removeLoadingScreen
};

export default window.utils;