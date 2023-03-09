$(function() {
    
    $('#entityAddForm').ajaxForm({
        url: config.dir.root +"/server/ajaxServer.php",
        dataType: "json",
        success: function (ret) {

            console.log(ret.text);
            return;

            $("#entityAddReturn").empty();
            $("input, select, textarea").css("border", "");

            if (ret["meta"]["requestStatus"] != "success") {
                for (let error in ret["errors"]) {
                    $("#entityAddReturn").append('<div>' + ret["errors"][error]["title"] + '</div>');
                    if ("label" in ret["errors"][error]) {
                        $("[name='" + ret["errors"][error]["label"] + "']").css("border", "1px solid red");
                    }
                }
            } else {

                $("#affectedSessions").empty();

                $(".contentContainer").not("#entityAddSuccess").slideUp();
                $("#entityAddSuccess").slideDown();

                if ("EntitysuggestionItem" in ret) {
                    $("#reimportSessions").data("entitysuggestionid", ret["EntitysuggestionItem"]["EntitysuggestionItemID"]);
                    if (("sessions" in ret) && (Object.keys(ret["sessions"]).length > 0)) {
                        for (let parliament in ret["sessions"]) {
                            let sessioncontent = "";
                            for (let session in ret["sessions"][parliament]) {
                                sessioncontent += "<div class='sessionFilesDiv'>" + session + " | File exists: " + ret["sessions"][parliament][session]["fileExists"] + "<input type='hidden' name='files[" + parliament + "][]' class='reimportfile' value='" + session + "'>";
                            }
                            $("#affectedSessions").append("<div class='parlamentDiv><h4>Parlament " + parliament + "</h4>" + sessioncontent + "</div>");
                        }
                        $("#affectedSessions_true").show();
                        $("#affectedSessions_false").hide();
                    } else {
                        $("#affectedSessions_false").show();
                        $("#affectedSessions_true").hide();
                    }

                } else {
                    $("#affectedSessions_false").show();
                    $("#affectedSessions_true").hide();
                }

            }
        }
    });


    $(".labelAlternativeAdd").on("click", function() {
        $(this).parent().find("div:first").append('<span style="position: relative">' +
            '<input type="text" class="form-control" name="labelAlternative[]">' +
            '<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
            '<span class="icon-cancel-circled"></span>' +
            '</button></span>');
    });



    $("body").on("click", ".labelAlternativeRemove", function() {
        $(this).parent().remove();
    });



    $(".socialMediaIDsAdd").on("click", function() {
        $(this).parent().find("div:first").append('<div style="position: relative" class="form-row">\n' +
            '                                        <div class="col">' +
            '                                           <input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)">' +
            '                                        </div>\n' +
            '                                        <div class="col">' +
            '                                           <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)">\n' +
            '                                        </div>\n' +
            '                                        <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
            '                                            <span class="icon-cancel-circled"></span>\n' +
            '                                        </button>\n' +
            '                                    </div>');
    });



    $("body").on("click", ".socialMediaIDsRemove", function() {
        $(this).parent().remove();
    });

    $("body").on("change", "select[name='entityType']", function() {
        let tempItem = "";
        switch ($(this).val()) {
            case "organisation":
                tempItem=".formItemTypeOrganisation";
            break;
            case "person":
                tempItem=".formItemTypePerson";
            break;
            case "term":
                tempItem=".formItemTypeTerm";
            break;
            case "document":
                tempItem=".formItemTypeDocument";
            break;
            default:
                tempItem=".not";
            break;
        }
        $("#entityAddForm .formItem input, #entityAddForm .formItem textarea, #entityAddForm .formItem select").prop("disabled",true);
        $(".formItem").slideUp(function() {
            $(tempItem +" input, "+tempItem+" textarea, "+tempItem+" select").prop("disabled",false);
            $(tempItem).slideDown();
        });


    });

    $("select[name='entityType']").val("person").trigger("change");

    /*
    $("body").on("click", ".entityaddform", function() {

        $(".contentContainer").not("#entityAddDiv").slideUp();
        $("#entityAddDiv").slideDown();
        $("#entityAddForm .formItem").hide();
        $("#entityAddForm")[0].reset();
        $("#entityAddForm .formItem input, #entityAddForm .formItem textarea, #entityAddForm .formItem select").prop("disabled",true);

        if ($(this).data("id")) {

            $.ajax({
                url: config.dir.root +"/server/ajaxServer.php",
                data: {"a":"entitysuggestionGet","id":$(this).data("id")},
                success: function(ret) {
                    if (ret["success"] == "true") {
                        $("input[name='id']").val(ret["return"]["EntitysuggestionExternalID"]);
                        $("input[name='label']").val(ret["return"]["EntitysuggestionLabel"]);
                        switch (ret["return"]["EntitysuggestionType"]) {
                            case "ORG":
                                $("select[name='entityType']").val("organisation").trigger("change");
                            break;

                            case "PERSON":
                                $("select[name='entityType']").val("person").trigger("change");
                            break;

                            case "DOC":
                                $("select[name='entityType']").val("document").trigger("change");
                            break;

                            case "TERM":
                            default:
                                $("select[name='entityType']").val("term").trigger("change");
                            break;
                        }
                        $(".contentContainer").not("#entityAddDiv").slideUp();
                        $("#entityAddDiv").slideDown();
                    }
                }
            });
        }
    })
    */


});