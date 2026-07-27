$(document).ready(function () {
  $('#geozone_gmap_radius').on('change', function () {
    codeAddress();
  });
  $('.geozone_gmap_input').on('keypress', function (event) {
    if (event.which === 13) {
      event.preventDefault();
      codeAddress();
    }
  });
});

var geocoder = "", map = "", circle = 0, marker = 0;
function initializeGMAP(apikey) {
	if($("#geozone_latitude").length || $("#geozoneAutoComplete").length){
		$.getScript("https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&language=ar&libraries=places&key="+apikey, function () {
			if($("#geozone_latitude").length){
				var lat = $("#geozone_latitude").val();
				var lng = $("#geozone_longitude").val();
				if(lat == ""){
				  lat = 23.885942;
				}
				if(lng == ""){
				  lng = 45.079162;
				}
				geocoder = new google.maps.Geocoder();
				var latlng = new google.maps.LatLng(lat, lng);
				var mapOptions = {
				  zoom: 5,
				  center: latlng
				}
				map = new google.maps.Map(document.getElementById('geozone-gmap'), mapOptions);
				if($('#geozone_gmap_radius').val() != ""){
				  drawMarker(latlng);
				}
				
				google.maps.event.addListener(map, 'click', function (event) {
					//console.log(event);
					$('#geozone_latitude').val(event.latLng.lat());
					$('#geozone_longitude').val(event.latLng.lng());
					drawMarker(event.latLng);
					geocodePosition(marker.getPosition());
				  });
			}
			if($("#geozoneAutoComplete").length){
				buildGPlaces();
			}
		});
	}
}

function drawCircle() {
  if (circle != 0) {
    circle.setMap(null);
  }
  var radius = parseInt($('#geozone_gmap_radius').val());
  if(isNaN(radius)){
	  radius = 70;
	  $('#geozone_gmap_radius').val(radius);
  }
  circle = new google.maps.Circle({
    map: map,
    radius: radius,
    fillColor: '#00AA00',
    strokeColor: '#fff',
    strokeOpacity: '.5',
    strokeWeight: '2'
  });
  circle.bindTo('center', marker, 'position');
}

function drawMarker(latlng){
  map.setCenter(latlng);
  map.setZoom(17);
  if (marker != 0) {
    marker.setMap(null);
  }
  marker = new google.maps.Marker({
    map: map,
    draggable: true,
    position: latlng
  });
  //console.log(latlng);
  $("#geozone_latitude").val(latlng.lat());
  $("#geozone_longitude").val(latlng.lng());
  drawCircle();

  google.maps.event.addListener(marker, 'dragend', function (event) {
    //console.log(event);
    $('#geozone_latitude').val(event.latLng.lat());
    $('#geozone_longitude').val(event.latLng.lng());
    drawCircle();
    geocodePosition(marker.getPosition());
  });
  
}

function codeAddress() {
  var address = $('#geozone_gmap_address').val();
  geocoder.geocode({'address': address}, function (results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      drawMarker(results[0].geometry.location);
    } else {
      alert('من فضلك ادخل العنوان المراد البحث عنه في المربع الموضح بالأعلى');
    }
  });
}

function geocodePosition(pos) {
  //console.log(pos);
  geocoder.geocode({
    latLng: pos
  }, function (responses) {
    if (responses && responses.length > 0) {
      $('#geozone_gmap_address').val(responses[0].formatted_address);
    } else {
      alert('لم يتم العثور على اي نتائج تطابق العنوان المدخل. حاول مرة اخرى بإستتخدام قيم مختلفة.');
    }
  });
}

function buildGPlaces() {
	var input = document.getElementById('geozoneAutoComplete');
	var options = {
		types: ['geocode'],
		componentRestrictions: {country: 'sa'}
	};
	var autocomplete = new google.maps.places.Autocomplete(input, options);
	autocomplete.addListener('place_changed', function() {
		var place = autocomplete.getPlace();
		if (!place.geometry) {
			window.alert("لم يتم العثور على اي نتائج");
			return;
		}
		var res = input.value.split(",");
		input.value = res[0];
	});
}