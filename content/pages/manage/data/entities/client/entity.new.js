$(function() {
    
    // Fill in wikidataID in case we got it in the url (eg. "?wikidataID=Q567")
    let queryWikidataID = getQueryVariable('wikidataID');
    if (queryWikidataID) {
        $('input[name="id"]').val(queryWikidataID);
    }

    // Fill in entitySuggestionID in case we got it in the url
    let queryEntitySuggestionID = getQueryVariable('entitySuggestionID');
    if (queryEntitySuggestionID) {
        $('input[name="entitysuggestionid"]').val(queryEntitySuggestionID);
    }

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

    $("#getAdditionalInfo").click(function(evt) {
        
        let entityType = $("select[name='entityType']").val();
        let subType = $("select[name='type']:not(:disabled)").val();

        let serviceType = entityType;
        if (subType == "memberOfParliament" || subType == "officialDocument") {
            serviceType = subType;
        }

        let wikidataID = $("input[name='id']").val();

        $.ajax({
            url:config.dir.root+"/server/ajaxServer.php",
            data: {
                "a": "entityGetFromAdditionalDataService",
                "type": serviceType,
                "wikidataID": wikidataID
            },
            success: function(result) {
                
                $("input[name='label']").val(result.data.label);
                $("input[name='firstName']").val(result.data.firstName);
                $("input[name='lastName']").val(result.data.lastName);
                $("input[name='degree']").val(result.data.degree);
                $("input[name='birthdate']").val(result.data.birthDate);
                $("textarea[name='abstract']").val(result.data.abstract);
                $("input[name='thumbnailuri']").val(result.data.thumbnailURI);
                $("input[name='thumbnailcreator']").val(result.data.thumbnailCreator);
                $("input[name='thumbnaillicense']").val(result.data.thumbnailLicense);
                //$("input[name='sourceuri']").val(result.data.);
                //$("input[name='embeduri']").val(result.data.);
                $("input[name='websiteuri']").val(result.data.websiteURI);
                //$("input[name='originid']").val(result.data.originID);
                $("textarea[name='additionalinformation']").val(JSON.stringify(result.data.additionalInformation));

                $("select[name='gender']").val(result.data.gender);
                $("select[name='party']").val(result.data.partyID);
                $("select[name='faction']").val(result.data.factionID);

                for (var i = result.data.labelAlternative.length - 1; i >= 0; i--) {
                    $("button.labelAlternativeAdd").next("div").append('<span style="position: relative">' +
                        '<input type="text" class="form-control" name="labelAlternative[]" value="'+ result.data.labelAlternative[i] +'">' +
                        '<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
                        '<span class="icon-cancel-circled"></span>' +
                        '</button></span>');
                }

                for (var i = result.data.socialMediaIDs.length - 1; i >= 0; i--) {
                    $("button.socialMediaIDsAdd").next("div").append('<div style="position: relative" class="form-row">\n' +
            '            <div class="col">' +
            '               <input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)" value="'+ result.data.socialMediaIDs[i].label +'">' +
            '            </div>\n' +
            '            <div class="col">' +
            '               <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)" value="'+ result.data.socialMediaIDs[i].id +'">\n' +
            '            </div>\n' +
            '            <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
            '                <span class="icon-cancel-circled"></span>\n' +
            '            </button>\n' +
            '        </div>');
                }
                
            }
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