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

	var addUserForm = $("#addProducthtype");

	var validator = addUserForm.validate({

		rules:{
			type_details : { required : true }
		},
		messages:{
			type_details : { required : "This field is required" }
		}
	});
});
