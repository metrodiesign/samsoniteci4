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

	var addUserForm = $("#addBrand");

	var validator = addUserForm.validate({

		rules:{
			brand_details : { required : true }
		},
		messages:{
			brand_details : { required : "This field is required" }
		}
	});
});
