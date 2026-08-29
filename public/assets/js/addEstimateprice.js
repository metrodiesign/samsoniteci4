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

	var addUserForm = $("#addEstimateprice");

	var validator = addUserForm.validate({

		rules:{
			estimateprice_details : { required : true }
		},
		messages:{
			estimateprice_details : { required : "This field is required" }
		}
	});
});
