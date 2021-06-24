window.pad = (n,s=2) => (`${new Array(s).fill(0)}${n}`).slice(-s);
$(function() {

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



});