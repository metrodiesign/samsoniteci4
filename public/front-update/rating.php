<?php include 'header.php'; ?>

<section id="rating">

  <div class="banner-control">
    <img class="rs-bg-size" src="images/bg-rs-tracking.png">
  </div>

  <div class="container">
    <div class="row">
      <div class="star-rating">
        <div class="topic-txt-hm" style="margin-bottom: 20px;">คะแนนบริการ</div>
        <span class="fa fa-star size" data-rating="1"></span>
        <span class="fa fa-star size" data-rating="2"></span>
        <span class="fa fa-star size" data-rating="3"></span>
        <span class="fa fa-star size" data-rating="4"></span>
        <span class="fa fa-star size" data-rating="5"></span>
        <input type="hidden" name="whatever1" class="rating-value" value="0">
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
  $(document).ready(function() {

  });

</script>

<?php include 'footer.php'; ?>
