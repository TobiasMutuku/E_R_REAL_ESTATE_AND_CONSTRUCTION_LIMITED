$(function () {
  $("#quoteForm").on("submit", function (event) {
    event.preventDefault();
    var form = this;
    var button = $("#quoteSubmit");
    var success = $("#quoteSuccess");
    button.prop("disabled", true);
    success.html("");
    $.ajax({
      url: form.action,
      type: "POST",
      data: new FormData(form),
      processData: false,
      contentType: false,
      success: function () {
        success.html(
          "<div class='alert alert-success'>Thank you. Your quote request has been received and our team will follow up.</div>",
        );
        form.reset();
      },
      error: function () {
        success.html(
          "<div class='alert alert-danger'>We could not send the request. Please call us or continue on WhatsApp.</div>",
        );
      },
      complete: function () {
        button.prop("disabled", false);
      },
    });
  });

  var property = new URLSearchParams(window.location.search).get("property");
  var service = new URLSearchParams(window.location.search).get("service");
  if (property) {
    $("#quoteMessage").val("I am interested in: " + property + ".");
    $("#quoteService").val("Property or land enquiry");
  }
  if (service) {
    $("#quoteMessage").val("I would like to discuss " + service + " services for my project.");
  }
});
