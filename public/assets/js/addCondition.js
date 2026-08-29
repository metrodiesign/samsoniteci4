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

	var addUserForm = $("#addCondition");

	var validator = addUserForm.validate({

		rules:{
			condition_details : { required : true }
		},
		messages:{
			condition_details : { required : "This field is required" }
		}
	});
});
