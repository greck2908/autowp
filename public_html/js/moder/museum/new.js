define("moder/museum/new",["jquery","googlemaps"],function(e,t){return{init:function(t){function a(e){o?o.setPosition(e):o=new google.maps.Marker({position:e,map:u})}function f(){o&&(o.setMap(null),o=null)}var n=new google.maps.LatLng(55.7423627,37.6786422),r=parseFloat(e("#lat").val()),i=parseFloat(e("#lng").val());i&&r&&(n=new google.maps.LatLng(r,i));var s=e('<div style="width:100%; height: 300px" />').insertAfter(e("#lng"))[0],o=null,u=new google.maps.Map(s,{zoom:2,center:n,mapTypeId:google.maps.MapTypeId.ROADMAP});i&&r&&a(n),e("#lng, #lat").change(function(){var t=parseFloat(e("#lat").val()),n=parseFloat(e("#lng").val());if(n&&t){var r=new google.maps.LatLng(t,n);a(r)}else f()}),google.maps.event.addListener(u,"click",function(t){a(t.latLng),e("#lng").val(t.latLng.lng()),e("#lat").val(t.latLng.lat())}),e("#address").change(function(){e.getJSON(t.addressToLatLngUrl,{address:e(this).val()},function(t){if(t){var n=new google.maps.LatLng(t.lat,t.lng);a(n),e("#lat").val(t.lat),e("#lng").val(t.lng)}})})}}});