(function($,Drupal){Drupal.behaviors.nicsdruOriginsThwoopImages={attach:function attach(context){var thwoopImageSelector='[data-picture-mapping*="_expandable"] > img, [data-picture-mapping*="_expandable"] > figure';var $thwoopImages=$(thwoopImageSelector,context);$thwoopImages.once("thwoop-toggle").each(function(){var $thwooper=$('<a class="thwooper" aria-label="expand image" href="#"></a>');$thwooper.bind("oanimationstart animationstart webkitAnimationStart",function(){$(this).parent().addClass("clearfix")});$thwooper.bind("oanimationend animationend webkitAnimationEnd",function(){if(!$(this).hasClass("thwooped"))$(this).parent().removeClass("clearfix")});$thwooper.click(function(event){event.preventDefault();var $thwoopimage=$(this).find("img, figure");var $thwoop_wrap=$(this).closest(".media-image");var is_thwooped=$(this).hasClass("thwooped");var thwooper_label=$(this).attr("aria-lable")==="expand image"?"shrink image":"expand image";var open_as_modal=!is_thwooped&&$thwoopimage.outerWidth()==$thwoop_wrap.outerWidth();$(this).toggleClass("thwooped",!is_thwooped);$(this).attr("aria-label",thwooper_label);if(open_as_modal){$thwoop_wrap.addClass("thwooped-modal");$(this).attr("aria-label","close image")}else if($thwoop_wrap.hasClass("thwooped-modal")){$thwoop_wrap.removeClass("thwooped-modal");$thwoop_wrap[0].scrollIntoView({block:"center"})}});$(this).wrap($thwooper)})}}})(jQuery,Drupal);