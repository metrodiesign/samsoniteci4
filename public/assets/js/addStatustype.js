/**
 * File : addBranch.js
 *
 * This file contain the validation of add Branch form
 *
 * Using validation plugin : jquery.validate.js
 *
 * @author Bunhan Poolumtan
 */

$(document).ready(function(){

	var addUserForm = $("#addStatustype");

	var validator = addUserForm.validate({

		rules:{
			description_th : { required : true },
			description_en : { required : true }
		},
		messages:{
			description_th : { required : "This field is required" },
			description_en: { required : "This field is required" }
		}
	});
});
