window.pad = (n,s=2) => (`${new Array(s).fill(0)}${n}`).slice(-s);
$(function() {

	/*
	var tableData = [];
	$.ajax({
		url: '../server/ajaxServer.php',
		method: "post",
		data: {
			a: "manageUsersGet"
		},
		tableData: tableData,
		success: function(ret) {
			if ("success" in ret) {
				if (ret["success"] == "true") {

					$.each(ret["data"], function(i,v) {
						tableData.push(v);
					});

					$('#manageUsersOverviewTable').bootstrapTable({
						data: tableData,
						columns: [{
							field: 'UserName',
							title: 'Name'
						}, {
							field: 'UserMail',
							title: 'Mail'
						}, {
							field: 'UserLastLogin',
							title: 'Last login',
							formatter: function(val) {
								if (val) {
									var ret = new Date(val * 1000);
									return pad(ret.getFullYear(),4)+"."+pad(ret.getMonth()+1)+"."+pad(ret.getDate())+" "+pad(ret.getHours())+":"+pad(ret.getMinutes())+":"+pad(ret.getSeconds());
								} else {
									return "Never";
								}

							}
						}, {
							field: 'UserActive',
							title: 'Active',
							formatter: function(val, row) {
								var tmpChecked = "";
								if (row["UserActive"] == 1) {
									return '<i class="icon-check"></i>';
								} else {
									return '<i class="icon-check-empty"></i>';
								}

							}
						}, {
							field: 'UserBlocked',
							title: 'Blocked',
							formatter: function(val, row) {
								var tmpChecked = "";
								if (row["UserBlocked"] == 1) {
									return '<i class="icon-check"></i>';
								} else {
									return '<i class="icon-check-empty"></i>';
								}
							}
						}, {
							field: 'UserID',
							title: 'Action',
							formatter: function(val, row) {

								return '<a href="./users/'+val+'"><i class="icon-pencil-1"></i></a>';
							}
						}
						]

					});
				} else {
					console.log("Error while loading Data #2");
				}
			} else {
				console.log("Error while loading Data #1");
			}

		}

	});

	*/

	$("#manageUsersOverviewTable").on("change", ".userform-useractive, .userform-userblocked", function() {
		//console.log(this);
		let item = this;
		let data = {};
		data["action"] = "change";
		data["itemType"] = "user";
		data["UserID"] = $(this).data("userid");
		data[$(this).attr("name")] = ((this.checked) ? 1 : 0);

		$.ajax({
			url: config["dir"]["root"]+"/api/v1/index.php",
			data: data,
			item: item,
			success: function(ret) {
				console.log(ret);
				if (ret["meta"]["requestStatus"] === "success") {
					$(item).animate({backgroundColor:"#d3f5e1"}, 1000, function() {
						$(item).animate({backgroundColor:""});
					})
				} else {
					$(item).animate({backgroundColor:"#f5d3dd"}, 1000, function() {
						$(item).animate({backgroundColor:""});
					})
				}
			}
		});

	});

	$("#manageUsersOverviewTable").on("keyup", ".userform-username, .userform-usermail, .userform-userrole, .userform-userpassword", function(e) {
		//console.log(this);
		//console.log(e.key);
		if (e.key === "Enter") {
			e.preventDefault();
			let item = this;
			let data = {};
			data["action"] = "change";
			data["itemType"] = "user";
			data["UserID"] = $(this).data("userid");
			data[$(this).attr("name")] = $(this).val();
			$.ajax({
				url: config["dir"]["root"]+"/api/v1/index.php",
				data: data,
				item: item,
				success: function(ret) {
					console.log(ret);
					if (ret["meta"]["requestStatus"] === "success") {
						$(item).animate({backgroundColor:"#d3f5e1"}, 1000, function() {
							$(item).animate({backgroundColor:""});
						})
					} else {
						$(item).animate({backgroundColor:"#f5d3dd"}, 1000, function() {
							$(item).animate({backgroundColor:""});
						})
					}
				}
			})
		}

	});





});