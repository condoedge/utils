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
            <i id="vl-spinner" class="icon-spinner" style="display: block;"></i>
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

import jquery from 'jquery';
window.$ = window.jQuery = jquery;

import { gsap } from 'gsap';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin';
import { MotionPathHelper } from 'gsap/MotionPathHelper';

gsap.registerPlugin(MotionPathPlugin, MotionPathHelper);
window.gsap = gsap;
window.MotionPathPlugin = MotionPathPlugin;
window.MotionPathHelper = MotionPathHelper;

import makeTutorialEngine from './tutorials/tutorial-engine';
const TutorialEngine = makeTutorialEngine(gsap, $); // Required in the next line
import initStepBuilder from './tutorials/step-builder/index';

const utils = {
	getYesNoValue: getYesNoValue,
	getToggle: getToggle,
	getToggleValue: getToggleValue,
	toggleYesNo: toggleYesNo,
	introJs: introJs,
	gsap: gsap,
	setLoadingScreen: setLoadingScreen,
	removeLoadingScreen: removeLoadingScreen,
	TutorialEngine,
	initStepBuilder
};

window.utils = utils;

export default window.utils;

export function decorateWindowWithUtils(window)
{
	window.utils = utils;
	window.gsap = gsap;
	window.MotionPathHelper = window.MotionPathHelper || (typeof MotionPathHelper !== 'undefined' ? MotionPathHelper : null);
	window.TutorialEngine = TutorialEngine;
	window.initStepBuilder = initStepBuilder;
	// initStepBuilder(TutorialEngine);
}