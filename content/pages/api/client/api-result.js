$(document).ready(function() {
  hljs.highlightAll();

  $('.apiRequestButton').click(function() {
    var apiURI = $(this).parents('.apiExampleContainer').find('.apiURI').val(),
        exampleCodeElem = $(this).parents('.apiExampleContainer').find('.apiResultContainer');

    $.ajax({
      method: "POST",
      url: apiURI
    }).done(function(data) {
      exampleCodeElem.empty();
      exampleCodeElem.jsonView(data);
    }).fail(function(err) {
      //console.log(err);
    });
  });
});