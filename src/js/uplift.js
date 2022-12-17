$(document).ready(function(){
    $("#header-nav-placeholder").load("header-navbar.html");

    $(document).on('submit', "#recipeForm", addRecipe);
    
});