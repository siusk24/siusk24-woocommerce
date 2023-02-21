function siusk24_get_selected_orders(form) {
    var div_id = "form_selected_orders";
    var old_div = document.getElementById(div_id);
    
    if (typeof(old_div) != 'undefined' && old_div != null) {
        old_div.remove();
    }
    
    var div = siusk24_build_div(div_id);

    var orders_cb = document.querySelectorAll('#filter-form .check-column input[type=checkbox]:checked');
    for (var i = 0; i < orders_cb.length; i++) {
        div.appendChild(siusk24_build_hidden_field("orders[]", orders_cb[i].value));
    }
    
    form.appendChild(div);
}

function siusk24_build_div(id) {
    var div = document.createElement("div");
    div.setAttribute("id", id);

    return div;
}

function siusk24_build_hidden_field(name, value) {
    var input = document.createElement("input");
    input.setAttribute("type", "hidden");
    input.setAttribute("name", name);
    input.setAttribute("value", value);

    return input;
}
