$(function() {
    function sleep(milliseconds) {
        const date = Date.now();
        let currentDate = null;
        do {
            currentDate = Date.now();
        } while (currentDate - date < milliseconds);
    }

    /**
     *
     *
     * TEXT
     *
     *
     */


    $("#media-text-body-button-add").on("click", function(item,event) {

        var itemTmpDate = "text-"+Date.now();
        var template =
            "<div class='media-text-item' id='"+itemTmpDate+"'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][type]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='type (proceedings)'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][sourceURI]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='sourceURI'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][creator]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='creator (Deutscher Bundestag)'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][license]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='license (Public Domain)'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][language]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='language (DE-de)'>" +
            "   <input type='text' name='textContents["+itemTmpDate+"][originTextID]' class='form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='originTextID'>" +
            "   <textarea name='textContents["+itemTmpDate+"][textBody]' class='form-control' id='textarea-"+itemTmpDate+"' data-itemTmp='"+itemTmpDate+"'></textarea>" +
            "   <button class='media-text-item-remove btn' type='button'><i class='icon-trash-1'></i></button><button class='media-text-item-convert btn' data-id='"+itemTmpDate+"' type='button'><i class='icon-align-left'></i><i class='icon-right'></i><i class='icon-database'></i></button>" +
            "</div>";

        $("#media-text-body").append(template);

    });

    $("#mediaAddForm").on("click", ".media-text-item-remove", function(e) {
        $(this).parent().remove();
    });

    $("#mediaAddForm").on("click", ".media-text-item-convert", function(e) {

        let item = $("#textarea-"+$(this).data("id"));
        let content = item.val().split("\n");
        let tmpName = "";
        let sentences = [];
        $.each(content, function() {

            if (this.match(/^##officialTitle\:/)) {

                $("#agendaItemOfficialTitle").val(this.replaceAll("#","").replace("officialTitle: ",""));

            } else if (this.match(/^##title\:/)) {

                $("#agendaItemTitle").val(this.replaceAll("#","").replace("title: ",""));

            } else if (this.match(/^##electoralPeriodNumber\:/)) {

                $("#electoralPeriodNumber").val(this.replaceAll("#","").replace("electoralPeriodNumber: ",""));

            } else if (this.match(/^##dateStart\:/)) {

                $("#sessionDateStart").val(this.replaceAll("#","").replace("dateStart: ",""));
                $("#dateStart").val(this.replaceAll("#","").replace("dateStart: ",""));

            } else if (this.match(/^##dateEnd\:/)) {

                $("#sessionDateEnd").val(this.replaceAll("#","").replace("dateEnd: ",""));

            } else if (this.match(/^##sessionNumber\:/)) {

                $("#sessionNumber").val(this.replaceAll("#","").replace("sessionNumber: ",""));

            } else if (this.match(/^##parliament\:/)) {

                $("#parliament").val(this.replaceAll("#","").replace("parliament: ",""));

            } else if (this.match(/^##videoFileURI\:/)) {

                $("#mediaVideoFileURI").val(this.replaceAll("#","").replace("videoFileURI: ",""));

            } else if (this.match(/^##sourcePage\:/)) {

                $("#mediaSourcePage").val(this.replaceAll("#","").replace("sourcePage: ",""));

            } else if (this.match(/^(Herr|Frau|Präsi|Vize|Mini).*[\:\*]$/)) {
                tmpName = this;
                let tmpAddNameNot = 0;

                $(".person-name").each(function() {

                   if (String($(this).val()) == String(tmpName)) {
                       tmpAddNameNot = 1;
                       return false;
                   }

                });

                if (tmpAddNameNot < 1) {
                    sleep(10);
                    let tmpNewPerson = addPeopleBlock();
                    $("input.person-name[data-itemtmp='"+tmpNewPerson+"']").val(tmpName);

                }
            } else if (this == "") {
                return true;
            } else if (this.match(/^\(.*\)$/)) {
                let tmpItem = {"type":"comment", "speaker":null, "speakerstatus":null, "sentences": [{"text":this}]};
                sentences.push(tmpItem);
            } else {
                let tmpItem = {"type":"speech", "speaker":tmpName, "speakerstatus":null, "text": this, "sentences": []};
                //let tmpSentences = this.split(/[\.\?\!]+/);
                let tmpSentences = this.replace(/(\.+|\:|\!|\?)(\"*|\'*|\)*|}*|]*)(\s|\n|\r|\r\n)/gm, "$1$2|").split("|");
                $.each(tmpSentences, function(){
                    if (this != "") {
                        tmpItem["sentences"].push({"text":this});
                    }
                });
                sentences.push(tmpItem);
            }

        });
        //console.log(sentences);
        item.val(JSON.stringify(sentences));
        //item.val(JSON.stringify(sentences, null, 2));
    });




    /**
     *
     *
     * PEOPLE
     *
     *
     */

    $("#media-people-body-button-add").on("click", addPeopleBlock);

    function addPeopleBlock () {
        var itemTmpDate = "person-"+Date.now();
        var template =
            "<div class='media-person-item' id='"+itemTmpDate+"'>" +
            "   <div class='fayt-container'>" +
            "       <div class='fayt-field'>" +
            "           <input name='people["+itemTmpDate+"][label]' class='person-name form-control mb-2' data-itemtmp='"+itemTmpDate+"' placeholder='Full Name' autocomplete='off'>" +
            "       </div>" +
            "       <div class='fayt-results' id='fayt-results-"+itemTmpDate+"'>" +
            "           <span class='fayr-results-label'>From Database</span>" +
            "           <ul id='fayt-results-db-"+itemTmpDate+"'></ul>" +
            "               <span class='fayr-results-label'>From Wikipedia</span>" +
            "           <ul id='fayt-results-wd-"+itemTmpDate+"'></ul>" +
            "       </div>" +
            "   </div>" +
            "   <input type='text' name='people["+itemTmpDate+"][wikidataID]' class='person-wikidataID form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='WikidataID'>" +
            "   <input type='text' name='people["+itemTmpDate+"][type]' class='person-type form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='Type (memberOfParliament)'>" +
            "   <input type='text' name='people["+itemTmpDate+"][context]' class='person-context form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='Context (mainSpeaker)'>" +
            "   <input type='text' name='people["+itemTmpDate+"][faction]' class='person-faction form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='Faction'>" +
            "   <input type='text' name='people["+itemTmpDate+"][party]' class='person-party form-control mb-2' data-itemTmp='"+itemTmpDate+"' placeholder='Party'>" +
            "   <a href='' class='disabled btn wikipedialink' target='_blank'><i class='icon-wikipedia-w'></i></a> <button class='media-person-item-remove btn' type='button'><i class='icon-trash-1'></i></button>" +
            "</div>";

        $("#media-people-body").append(template);
        return itemTmpDate;
    }



    $("#mediaAddForm").on("keyup", ".person-name", function(e) {


        var itemTmp = $(this).data("itemtmp");

        if ($(this).val().length > 2) {
            $.ajax({
                url:config["dir"]["root"]+"/api/v1/search/people", //TODO: Move API to root $config and add it to JS Object
                data: {
                    "name":$(this).val()
                },
                tmpID: itemTmp,
                success: function(ret) {

                    let tmpID = this.tmpID;
                    $("#fayt-results-db-"+tmpID).html("");

                    if (ret["meta"]["requestStatus"] == "success") {

                        if (ret["data"].length > 0) {

                            var maxItems = 5;
                            var currItem = 0;

                            $(ret["data"]).each(function(i) {
                                currItem++;
                                if (currItem < maxItems) {
                                    $("#fayt-results-db-"+tmpID).append(
                                        "<li class='fayt-result-person-item fayt-result-db-person-item' " +
                                        "data-itemtmp='"+tmpID+"' " +
                                        "data-wikidataid='"+ret.data?.[i]?.id+"' " +
                                        "data-party='"+ret["data"][i]?.relationships?.party?.data?.id+"' " +
                                        "data-faction='"+ret["data"][i]?.relationships?.faction?.data?.id+"' " +
                                        "data-type='"+ret["data"][i]?.attributes?.type+"' " +
                                        "data-label='"+ret["data"][i]?.attributes?.label+"'>"+
                                        ret["data"][i]?.attributes?.label+" " +
                                        "("+ret["data"][i]?.relationships?.party?.data?.attributes?.labelAlternative+")</li>");
                                    //$("#fayt-results-db-"+tmpID).append("<li class='fayt-result-person-item fayt-result-db-person-item' data-itemtmp='"+tmpID+"' data-wikidataid='"+ret["data"][i]?.["id"]+"' data-party='"+ret["data"][i]?.["relationships"]?["party"]?.["data"]?.["id"]+"' data-faction='"+ret["data"][i]?.["relationships"]?.["faction"]?.["data"]?.["id"]+"' data-label='"+ret["data"][i]?.["attributes"]?.["label"]+"'>"+ret["data"][i]?.["attributes"]?.["label"]+" ("+ret["data"][i]?.["relationships"]?.["party"]?.["data"]?.["attributes"]?.["labelAlternative"]+")</li>");
                                } else {
                                    return;
                                }

                            });

                        } else {

                            $("#fayt-results-db-"+tmpID).append("<li class='fayt-result-none'>Nothing found.</li>");

                        }
                        $("#fayt-results-"+tmpID).show();
                    }
                }
            });

            $.ajax({
                url:config["dir"]["root"]+"/api/v1/index.php", //TODO: Move API to root $config and add it to JS Object
                data: {
                    "action":"wikidataService",
                    "itemType":"person",
                    "str":$(this).val()
                },
                tmpID: itemTmp,
                success: function(ret) {

                    let tmpID = this.tmpID;

                    $("#fayt-results-wd-"+tmpID).html("");

                    if (ret["meta"]["requestStatus"] == "success") {


                        if (ret?.["data"].length > 0) {

                            var maxItems = 8;
                            var currItem = 0;

                            $(ret["data"]).each(function(i) {
                                currItem++;
                                if (currItem < maxItems) {

                                    $("#fayt-results-wd-" + tmpID).append(
                                        "<li class='fayt-result-person-item fayt-result-db-person-item' " +
                                        "data-itemtmp='" + tmpID + "' " +
                                        "data-wikidataid='" + ret.data?.[i]?.id + "' " +
                                        "data-party='" + ret.data?.[i]?.partyID + "' " +
                                        "data-faction='" + ret.data?.[i]?.factionID + "' " +
                                        "data-type='" + ret.data?.[i]?.type + "' " +
                                        "data-label='" + ret.data?.[i]?.label + "'>" +
                                        ret.data?.[i]?.label + " (" + ret.data?.[i]?.parliament + ")</li>");

                                } else {
                                    return;
                                }
                            });

                        } else {

                            $("#fayt-results-wd-"+tmpID).html("<li class='fayt-result-none'>Nothing found.</li>");

                        }

                        $("#fayt-results-"+tmpID).show();

                    } else {

                        $("#fayt-results-wd-"+tmpID).html("<li class='fayt-result-none'>Database error.</li>");

                    }
                }
            })
        }


    });

    $("#mediaAddForm").on("focus", ".person-name", function(e) {

        if ($(this).val() != "") {
            var itemTmp = $(this).data("itemtmp");
            $("#fayt-results-"+itemTmp).show();
        }

    });

    $("#mediaAddForm").on("focusout", ".person-name", function(e) {

        if ($(".media-person-item").find(".fayt-result-person-item:hover").length) {
            return false;
        }

        var itemTmp = $(this).data("itemtmp");
        $("#fayt-results-"+itemTmp).hide();

    });

    $("#mediaAddForm").on("click", ".fayt-result-person-item", function(e) {
        //console.log(this);
        $("#"+$(this).data("itemtmp")+" .person-name").val($(this).data("label"));
        $("#"+$(this).data("itemtmp")+" .person-wikidataID").val($(this).data("wikidataid"));
        $("#"+$(this).data("itemtmp")+" .person-faction").val($(this).data("faction"));
        $("#"+$(this).data("itemtmp")+" .person-type").val($(this).data("type"));
        $("#"+$(this).data("itemtmp")+" .person-party").val($(this).data("party"));
        if ($(this).data("wikidataid")) {
            $("#"+$(this).data("itemtmp")+" .wikipedialink").attr("href","https://www.wikidata.org/wiki/"+$(this).data("wikidataid")).removeClass("disabled");
        } else {
            $("#"+$(this).data("itemtmp")+" .wikipedialink").attr("href","").addClass("disabled");
        }

        $("#fayt-results-"+$(this).data("itemtmp")).hide();
    });

    $("#mediaAddForm").on("click", ".media-person-item-remove", function(e) {
        $(this).parent().remove();
    });







    /**
     *
     *
     * DOCUMENTS
     *
     *
     */

    $("#media-documents-body-button-add").on("click", function(item,event) {

        var itemTmpDate = "document-"+Date.now();
        var template =
            "<div class='media-documents-item' id='"+itemTmpDate+"'>" +
            "   <div class='fayt-container'>" +
            "       <div class='fayt-field'>" +
            "           <input name='document["+itemTmpDate+"][label]' class='document-label' data-itemtmp='"+itemTmpDate+"' placeholder='label' autocomplete='off'>" +
            "       </div>" +
            "       <div class='fayt-results' id='fayt-results-"+itemTmpDate+"'>" +
            "           <span class='fayr-results-label'>From Database</span>" +
            "           <ul id='fayt-results-db-"+itemTmpDate+"'></ul>" +
            "       </div>" +
            "   </div>" +
            "   <input type='text' name='document["+itemTmpDate+"][labelAlternative]' class='document-labelAlternative' data-itemTmp='"+itemTmpDate+"' placeholder='labelAlternative'>" +
            "   <input type='text' name='document["+itemTmpDate+"][wikidataID]' class='document-wikidataID' data-itemTmp='"+itemTmpDate+"' placeholder='WikidataID'>" +
            "   <input type='text' name='document["+itemTmpDate+"][id]' class='document-id' data-itemTmp='"+itemTmpDate+"' placeholder='documentID' readonly>" +
            "   <input type='text' name='document["+itemTmpDate+"][type]' class='document-type' data-itemTmp='"+itemTmpDate+"'placeholder='Type (officialDocument)'>" +
            "   <input type='text' name='document["+itemTmpDate+"][abstract]' class='document-abstract' data-itemTmp='"+itemTmpDate+"' placeholder='abstract'>" +
            "   <input type='text' name='document["+itemTmpDate+"][thumbnailURI]' class='document-thumbnailuri' data-itemTmp='"+itemTmpDate+"' placeholder='thumbnailURI'>" +
            "   <input type='text' name='document["+itemTmpDate+"][thumbnailCreator]' class='document-thumbnailcreator' data-itemTmp='"+itemTmpDate+"' placeholder='thumbnailCreator'>" +
            "   <input type='text' name='document["+itemTmpDate+"][thumbnailLicense]' class='document-thumbnaillicense' data-itemTmp='"+itemTmpDate+"' placeholder='thumbnailLicense'>" +
            "   <input type='text' name='document["+itemTmpDate+"][sourceURI]' class='document-sourceuri' data-itemTmp='"+itemTmpDate+"' placeholder='sourceURI'>" +
            "   <input type='text' name='document["+itemTmpDate+"][embedURI]' class='document-embeduri' data-itemTmp='"+itemTmpDate+"' placeholder='embedURI'>" +
            "   <input type='text' name='document["+itemTmpDate+"][additionalInformation]' class='document-additionalinformation' data-itemTmp='"+itemTmpDate+"' placeholder='additionalInformation'>" +
            "   <button class='media-person-item-remove btn' type='button'><i class='icon-trash-1'></i></button>" +
            "</div>";

        $("#media-documents-body").append(template);

    });

    $("#mediaAddForm").on("keyup", ".document-label", function(e) {


        var itemTmp = $(this).data("itemtmp");

        if ($(this).val().length > 2) {
            $.ajax({
                url:config["dir"]["root"]+"/api/v1/search/documents", //TODO: Move API to root $config and add it to JS Object
                data: {
                    "label":$(this).val()
                },
                tmpID: itemTmp,
                success: function(ret) {

                    if (ret["meta"]["requestStatus"] == "success") {

                        let tmpID = this.tmpID

                        $("#fayt-results-db-"+tmpID).html("");

                        if (ret["data"].length > 0) {

                            var maxItems = 5;
                            var currItem = 0;

                            $(ret["data"]).each(function(i) {
                                currItem++;
                                if (currItem < maxItems) {

                                    $("#fayt-results-db-"+tmpID).append(
                                        "<li class='fayt-result-document-item fayt-result-db-document-item'" +
                                        "data-itemtmp='"+tmpID+"' " +
                                        "data-id='"+ret.data?.[i]?.id+"' " +
                                        "data-wikidataid='"+ret.data?.[i]?.attributes?.wikidataID+"' " +
                                        "data-label='"+ret["data"][i]?.attributes?.label+"' " +
                                        "data-labelalternative='"+ret["data"][i]?.attributes?.labelAlternative+"' " +
                                        "data-abstract='"+ret["data"][i]?.attributes?.abstract+"' " +
                                        "data-thumbnailuri='"+ret["data"][i]?.attributes?.thumbnailURI+"' " +
                                        "data-thumbnailcreator='"+ret["data"][i]?.attributes?.thumbnailCreator+"' " +
                                        "data-thumbnaillicense='"+ret["data"][i]?.attributes?.thumbnailLicense+"' " +
                                        "data-sourceuri='"+ret["data"][i]?.attributes?.sourceURI+"' " +
                                        "data-embeduri='"+ret["data"][i]?.attributes?.embedURI+"' " +
                                        "data-additionalinformation='"+ret["data"][i]?.attributes?.additionalInformation+"'> " +
                                        ret["data"][i]?.attributes?.label+" ("+ret["data"][i]?.attributes?.labelAlternative+")</li>");
                                } else {
                                    return;
                                }

                            });

                        } else {

                            $("#fayt-results-db-"+tmpID).append("<li class='fayt-result-none'>Nothing found.</li>");

                        }
                        $("#fayt-results-"+tmpID).show();
                    }
                }
            });

        }


    });

    $("#mediaAddForm").on("focus", ".document-label", function(e) {

        if ($(this).val() != "") {
            var itemTmp = $(this).data("itemtmp");
            $("#fayt-results-"+itemTmp).show();
        }

    });

    $("#mediaAddForm").on("focusout", ".document-label", function(e) {

        if ($(".media-documents-item").find(".fayt-result-document-item:hover").length) {
            return false;
        }

        var itemTmp = $(this).data("itemtmp");
        $("#fayt-results-"+itemTmp).hide();

    });

    $("#mediaAddForm").on("click", ".fayt-result-document-item", function(e) {
        //console.log(this);
        $("#"+$(this).data("itemtmp")+" .document-label").val($(this).data("label"));
        $("#"+$(this).data("itemtmp")+" .document-labelAlternative").val($(this).data("labelalternative"));
        $("#"+$(this).data("itemtmp")+" .document-wikidataID").val($(this).data("wikidataid"));
        $("#"+$(this).data("itemtmp")+" .document-id").val($(this).data("id"));
        $("#"+$(this).data("itemtmp")+" .document-abstract").val($(this).data("abstract"));
        $("#"+$(this).data("itemtmp")+" .document-thumbnailuri").val($(this).data("thumbnailuri"));
        $("#"+$(this).data("itemtmp")+" .document-thumbnailcreator").val($(this).data("thumbnailcreator"));
        $("#"+$(this).data("itemtmp")+" .document-thumbnaillicense").val($(this).data("thumbnaillicense"));
        $("#"+$(this).data("itemtmp")+" .document-sourceuri").val($(this).data("sourceuri"));
        $("#"+$(this).data("itemtmp")+" .document-embeduri").val($(this).data("embeduri"));
        $("#"+$(this).data("itemtmp")+" .document-additionalinformation").val($(this).data("additionalinformation"));
        if ($(this).data("wikidataid")) {
            $("#"+$(this).data("itemtmp")+" .wikipedialink").attr("href","https://www.wikidata.org/wiki/"+$(this).data("wikidataid")).removeClass("disabled");
        } else {
            $("#"+$(this).data("itemtmp")+" .wikipedialink").attr("href","").addClass("disabled");
        }

        $("#fayt-results-"+$(this).data("itemtmp")).hide();
    });

    $("#mediaAddForm").on("click", ".media-document-item-remove", function(e) {
        $(this).parent().remove();
    });

    $("#mediaAddForm").ajaxForm({
        type:"POST",
        data:{
          action:"addMedia",
          itemType:"media"
        },
        url: config["dir"]["root"]+"/api/v1/index.php",
        complete: function(r) {
            console.log(r);
            console.log(r.responseText);
        }
    })

});