<section id="rating">

  <div class="banner-control">
    <img class="rs-bg-size" src="<?php echo base_url(); ?>uploads/web/trackstatus_laptop.png">
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating mb-0">
        <div class="topic-txt-hm" style="color: #ff6600; margin-bottom: 20px;">กรุณาประเมินความพึงพอใจในบริการของเราเพื่อช่วยให้เราพัฒนาได้ดียิ่งขึ้น</div>
        <div class="topic-txt-hm-sm" style="margin-bottom: 20px;">1. ไม่พอใจมาก / 2. ไม่พอใจ / 3. ปานกลาง / 4. พอใจ / 5. พอใจมาก</div>
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม</div>
        <span class="fa fa-star size" data-rating="1"></span>
        <span class="fa fa-star size" data-rating="2"></span>
        <span class="fa fa-star size" data-rating="3"></span>
        <span class="fa fa-star size" data-rating="4"></span>
        <span class="fa fa-star size" data-rating="5"></span>
        <input type="hidden" name="whatever1" id="whatever1" class="rating-value" value="0">
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">2. ความพึงพอใจในการให้บริการของศูนย์บริการ</div>
        <span class="fa fa-star size" data-centerrating="1"></span>
        <span class="fa fa-star size" data-centerrating="2"></span>
        <span class="fa fa-star size" data-centerrating="3"></span>
        <span class="fa fa-star size" data-centerrating="4"></span>
        <span class="fa fa-star size" data-centerrating="5"></span>
        <input type="hidden" name="centerwhatever1" id="centerwhatever1" class="centerrating-value" value="0">
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">3. ความพึงพอใจในคุณภาพงานซ่อม</div>
        <span class="fa fa-star size" data-centerrating="1"></span>
        <span class="fa fa-star size" data-centerrating="2"></span>
        <span class="fa fa-star size" data-centerrating="3"></span>
        <span class="fa fa-star size" data-centerrating="4"></span>
        <span class="fa fa-star size" data-centerrating="5"></span>
        <input type="hidden" name="rating_3" id="rating_3" class="centerrating-value" value="0">
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">4. ระยะเวลาที่ใช้ในการซ่อม</div>
        <span class="fa fa-star size" data-centerrating="1"></span>
        <span class="fa fa-star size" data-centerrating="2"></span>
        <span class="fa fa-star size" data-centerrating="3"></span>
        <span class="fa fa-star size" data-centerrating="4"></span>
        <span class="fa fa-star size" data-centerrating="5"></span>
        <input type="hidden" name="rating_4" id="rating_4" class="centerrating-value" value="0">
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">5. ลำดับความสำคัญที่ลูกค้าพิจารณา<br>ระยะเวลาซ่อม / ค่าบริการซ่อม / คุณภาพงานซ่อม / ความพึงพอใจในการบริการ</div>
        <span class="fa fa-star size" data-centerrating="1"></span>
        <span class="fa fa-star size" data-centerrating="2"></span>
        <span class="fa fa-star size" data-centerrating="3"></span>
        <span class="fa fa-star size" data-centerrating="4"></span>
        <span class="fa fa-star size" data-centerrating="5"></span>
        <input type="hidden" name="rating_5" id="rating_5" class="centerrating-value" value="0">
      </div>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating-center mb-0">
        <div class="topic-txt-hm-md" style="margin-bottom: 20px;">6. ข้อเสนอแนะเพิ่มเติม</div>
        <div class="col-xs-12 col-md-10 col-md-offset-1">
          <textarea class="form-control" rows="6"></textarea>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top: 30px; margin-bottom: 20px;" class="container">
    <div class="row">
      <div class="col-md-12" style="text-align: center;">
        <input type="submit" class="main-btn-sm" value="SUBMIT" id="submit_id">
      </div>
    </div>
  </div>

</section>

<script type="text/javascript">
  var $star_rating = $('.star-rating .fa');

  var SetRatingStar = function() {
    return $star_rating.each(function() {
      if (parseInt($star_rating.siblings('input.rating-value').val()) >= parseInt($(this).data('rating'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  $star_rating.on('click', function() {
    $star_rating.siblings('input.rating-value').val($(this).data('rating'));
    return SetRatingStar();

  });

  SetRatingStar();

  var $star_rating_center = $('.star-rating-center .fa');

  var SetRatingStarCenter = function() {
    return $star_rating_center.each(function() {
      if (parseInt($star_rating_center.siblings('input.centerrating-value').val()) >= parseInt($(this).data('centerrating'))) {
        return $(this).removeClass('fa-star-o').addClass('fa-star');
      } else {
        return $(this).removeClass('fa-star').addClass('fa-star-o');
      }
    });
  };

  $star_rating_center.on('click', function() {

    $star_rating_center.siblings('input.centerrating-value').val($(this).data('centerrating'));


    return SetRatingStarCenter();

  });


  SetRatingStarCenter();
  $("#submit_id").click(function() {
    var order_id = "<?php echo $order_id; ?>";
    var branchID = "<?php echo $bid; ?>";
    var base_url = "<?php echo base_url(); ?>";
    var centerating = $('#centerwhatever1').val();
    var rating = $('#whatever1').val();
    if (centerating == 0) {
      alert("กรุณาลงคะแนน");
    } else if (rating == 0) {
      alert("กรุณาลงคะแนน");
    } else {
      $.ajax({
        method: "POST",
        url: base_url + "rating/addRating",
        data: {
          orderid: order_id,
          rating: rating,
          add: 1,
          branchID: branchID
        }
      }).done(function(msg) {
        if (msg == "1") {
          //alert("ลงคะแนนพนักงานผู้บริการ");
          $.ajax({
            method: "POST",
            url: base_url + "rating/addRating",
            data: {
              orderid: order_id,
              rating: centerating,
              add: 2,
              branchID: branchID
            }
          }).done(function(xmsg) {
            if (xmsg == "1") {
              alert("ขอบคุณสำหรับความคิดเห็น");
              location.href = "https://track.houseofsamsonite.co.th";
            } else {
              alert("ท่านได้แสดงความคิดเห็นไปแล้ว");
              location.href = "https://track.houseofsamsonite.co.th";
            }
          });
        } else {
          alert("ท่านได้แสดงความคิดเห็นไปแล้ว");
          location.href = "https://track.houseofsamsonite.co.th";
        }
      });
    }

  });
  $(document).ready(function() {

  });
</script>