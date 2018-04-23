$(document).ready(function() {
  hljs.initHighlightingOnLoad();
  

  // submit form on ctrl+enter
  $('#wrap_Inputfield_code').keydown(function (e) {
    if ((e.ctrlKey || e.altKey) && e.keyCode == 13) {
      $('#submit').click();
    }
  });

});