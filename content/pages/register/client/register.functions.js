$(function() {

	$("#register-form").ajaxForm({
		url: "server/ajaxServer.php",
		data: {"a":"registerUser"},
		dataType: "json",
		method:"POST",
		success: function(ret) {

			if (typeof ret !== 'undefined') {

				if (ret["success"] == "true") {

					$("#register-response").text("Registration successful. You will be redirected");
					setTimeout(function() {
						location.reload();
					},2000);


				} else {

					$("#register-response").text("Your registration failed.: "+ret["txt"]);
					console.log(ret["txt"]);

				}

			} else {

				$("#register-response").text("There was an error (code #02) while registration. Please try again");

			}
		},
		error: function() {

			$("#register-response").text("There was an error (code #01) while registration. Please try again");

		}
	});

});