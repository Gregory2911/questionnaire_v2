
document.addEventListener("DOMContentLoaded", function () {
  "use strict";
 
  var button = document.querySelector("button.validation");
  
  button.addEventListener("click", function (event) {
    var forms = document.getElementsByClassName('needs-validation');

    $('.choixMultiple').each(function(){
      var ok = false;
      $(this).find('input').each(function(){
        if($(this).is(':checked')){
          ok = true;            
        }
      })

      $(this).find('input').each(function(){        
        if(ok === false){          
          $(this).attr("required", true);
        }
        else{
          $(this).attr("required", false);
        }        
      })

    });

    //Loop over them and prevent submission
    var validation = Array.prototype.filter.call(forms, function(form) {        
        if (form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
          form.classList.add('was-validated');        
        }        
        else
        {
          // if (confirm("Confirmez-vous l'envoi de vos réponses ?"))
          // {      
          //   formulaire.submit();      
          // };
          $('#modalConfirmation').modal('show');      
        }          
    });    
  });

  // var checkbox = document.querySelectorAll('input.custom-control-input');
  // for (var i = 0; i <= checkbox.length; i++){
  //   checkbox[i].addEventListener("click", function(ev){
  //     //alert('cool');
  //     $(this).addClass("done");
  //   })
  // };
  $(":checkbox").click(function(){
    // alert('cool');
    $(this).addClass("done");
  })

  $("#btnConfirmation").click(function(){
    formulaire.submit();
    $('#modalConfirmation').modal('hide');      
  })
});