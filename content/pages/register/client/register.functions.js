$(function() {

	$("#register-form").ajaxForm({
		url: "server/ajaxServer.php",
		data: {"a":"registerUser"},
		dataType: "json",
		method:"POST",
		success: function(ret) {

			if (typeof ret !== 'undefined') {

				if (ret["success"] == "true") {

					$("#register-response").removeClass("alert-danger").addClass("alert-success").text(ret["txt"]);
					setTimeout(function() {
						location.reload();
					},2000);


				} else {

					$("#register-response").removeClass("alert-success").addClass("alert-danger").html(ret["txt"]);
					//console.log(ret["txt"]);

				}

			} else {

				$("#register-response").removeClass("alert-success").addClass("alert-danger").text("There was an error (code #02) while registering your account. Please try again");

			}
		},
		error: function() {

			$("#register-response").removeClass("alert-success").addClass("alert-danger").text("There was an error (code #01) while registering your account. Please try again");

		}
	});

});