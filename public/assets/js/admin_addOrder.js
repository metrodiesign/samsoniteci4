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

	var addUserForm = $("#addOrder");

	var validator = addUserForm.validate({

		rules:{

			requestDate : { required : true },
			branch_type :{ required : true, selected : true},
			branch_id :{ required : true, selected : true},
			bookshort : { required : true },
			customerFullname : { required : true },
			customerTel : { required : true },
			detailTypeId :{ required : true, selected : true},
			detailBrandId :{ required : true, selected : true}
		},
		messages:{
			requestDate : { required : "This field is required" },
			branch_type :{ required : "This field is required", selected : "Please select atleast one option" },
			branch_id :{ required : "This field is required", selected : "Please select atleast one option" },
			bookshort : { required : "This field is required" },
			customerFullname : { required : "This field is required" },
			customerTel : { required : "This field is required" },
			detailTypeId :{ required : "This field is required", selected : "Please select atleast one option" },
			detailBrandId :{ required : "This field is required", selected : "Please select atleast one option" }
		}
	});
});
