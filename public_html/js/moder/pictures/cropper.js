define("moder/pictures/cropper",["jquery","jcrop"],function(e){return{init:function(t){var n=t.crop;e("#picture-to-crop").Jcrop({onSelect:function(e){n=e},setSelect:[t.crop.x,t.crop.y,t.crop.x+t.crop.w,t.crop.y+t.crop.h],aspectRatio:4/3,minSize:[400,300],boxWidth:t.width/2,boxHeight:t.height/2,trueSize:[t.width,t.height]}),e("#save-crop").click(function(){var r=e(this).button("loading");e.post(t.saveUrl,n,function(){r.button("reset")})})}}});