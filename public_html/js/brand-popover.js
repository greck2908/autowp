define("brand-popover",["jquery","bootstrap"],function(e,t){return{apply:function(t){e(t).each(function(){var t=this,n=!1,r=!1;e(this).on("click",function(e){e.preventDefault()}).hover(function(){r=!0,n?e(this).popover("show"):e.get(e(this).attr("href"),{},function(i){function s(t,n){var r=window.innerWidth;if(r<500)return"bottom";var i=e(n).offset().left;return r-i>400?"right":"left"}e(t).popover({trigger:"manual",content:i,html:!0,placement:"bottom"}),n=!0,r&&e(t).popover("show")})},function(){r=!1,n&&e(this).popover("hide")})})}}});