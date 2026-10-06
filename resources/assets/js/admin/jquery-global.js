// SB Admin 2, Bootstrap 4 and jQuery plugins expect a global jQuery.
// This module must be imported before them (ES imports are evaluated in order).
import jQuery from 'jquery';

window.$ = window.jQuery = jQuery;

export default jQuery;
