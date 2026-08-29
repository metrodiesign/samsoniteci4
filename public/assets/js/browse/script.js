$(function(){

    var ul = $('#upload ul');

    $('#drop a').click(function(){
        // Simulate a click on the file input button
        // to show the file browser dialog
        $(this).parent().find('input').click();

    });

    // Initialize the jQuery File Upload plugin
    $('#upload').fileupload({


        dropZone: $('#drop'),

        // This function is called when a file is added to the queue;
        // either via the browse button, or via drag/drop:
        add: function (e, data) {
          if (data.files && data.files[0]) {
          var reader = new FileReader();
          reader.onload = function(e) {
            var $flagEXT = false;
        		var $filetest = "";
            var fileSize = data.files[0].size; //size in kb
            var jsurl=xtimesite+data.files[0].name;
          //  alert(e.target.result);
            //console.log(data);
       			fileSize = fileSize / 1048576;

            $flagEXT = validateVideoFileExtension_img(data.files[0].name,fileSize);
              var tpl = $('<input type="hidden" name="fiel_name" value="'+data.files[0].name+'"><li class="working"><img src="'+e.target.result+'" width="60" height="50" border="0" /></p><span></span>'+
              '</li>');

              // Append the file name and file size
              tpl.find('p').text(data.files[0].name).append('<i>' + formatFileSize(data.files[0].size) + '</i>');

              // Add the HTML to the UL element
              if($flagEXT==true){
          			//alert($filetest);
                data.context = tpl.appendTo(ul);

                // Initialize the knob plugin
                tpl.find('input').knob();

                // Listen for clicks on the cancel icon
                tpl.find('span').click(function(){

                    if(tpl.hasClass('working')){
                        jqXHR.abort();
                    }

                    tpl.fadeOut(function(){
                        tpl.remove();
                    });

                });
          		}else{
          			window.alert('กรุณาเลือกไฟล์ประเภท image เท่านั้น (!! invalid extension)');
          			//document.getElementById('upload_file_thumb').innerHTML='';
          			return false;
          		}

          }
          reader.readAsDataURL(data.files[0]);
          var jqXHR = data.submit();
          }



            // Automatically upload the file once it is added to the queue

        },

        progress: function(e, data){

            // Calculate the completion percentage of the upload
            var progress = parseInt(data.loaded / data.total * 100, 10);

            // Update the hidden input field and trigger a change
            // so that the jQuery knob plugin knows to update the dial
            data.context.find('input').val(progress).change();

            if(progress == 100){
              //  data.context.removeClass('working');
            }
        },

        fail:function(e, data){
            // Something has gone wrong!
            data.context.addClass('error');
        }

    });


    // Prevent the default action when a file is dropped on the window
    $(document).on('drop dragover', function (e) {
        e.preventDefault();
    });

    // Helper function that formats the file sizes
    function formatFileSize(bytes) {
        if (typeof bytes !== 'number') {
            return '';
        }

        if (bytes >= 1000000000) {
            return (bytes / 1000000000).toFixed(2) + ' GB';
        }

        if (bytes >= 1000000) {
            return (bytes / 1000000).toFixed(2) + ' MB';
        }

        return (bytes / 1000).toFixed(2) + ' KB';
    }
    function validateVideoFileExtension_img($file_field,$fileSize){

    	var ext = $file_field.split('.').pop().toLowerCase();
    	if($.inArray(ext, ['gif','jpg','jpe','jpeg', 'png']) == -1){
    		return false;
    	}else{
    		if($fileSize>500){
    		return false;
    		}else{
    		return true;
    		}
    	}
    }

});
