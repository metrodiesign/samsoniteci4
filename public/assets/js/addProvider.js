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

	var addUserForm = $("#addProvider");

	var validator = addUserForm.validate({

		rules:{
			provider_name : { required : true },
			provider_tel : { required : true }
		},
		messages:{
			provider_name :{ required : "This field is required" },
			provider_tel : { required : "This field is required" }
		}
	});
});
