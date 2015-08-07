jQuery(document).ready(function ($) {
	$("#wptm_meta_form").bind("submit",function() {
		var name = $(this).find("#new_meta_name").val();
		if(name == "")
		{
			alert(wptm.name_is_empty);
			return false;
		}
		var taxcount = $("input[name='new_meta_taxonomy[]']:checked").length;
		if(taxcount == 0)
		{
			alert(wptm.no_taxonomies);
			return false;
		}
		return true;
	}
	);
	
	$("#wptm_meta_form").bind("submit",function() {
		var action = $(this).find("#wptm_action").val();
		var new_tax = $(this).find("#new_meta_taxonomy").val();
		var old_tax = $(this).find("#old_meta_taxonomy").val();
		if(action == "edit" && old_tax !== new_tax)
		{
			return confirm(wptm.taxonomy_changed_warning);
		}
		return true;
	});
	$(".wptm_add_row").click(function(){
		var add_row = $(this).parent("td").closest("tr.values");
		var new_add_row = add_row.clone(true,true);
		new_add_row.insertAfter(add_row);
		new_add_row.find(".new_meta_names").val("");
		new_add_row.find(".new_meta_values").val("");
	});
	$(".wptm_delete_row").click(function(){
		if($("tr.values").length  > 1) $(this).parent("td").closest("tr.values").remove();
		else {
			$(this).parent("td").closest("tr.values").find(".new_meta_names").val("");
			$(this).parent("td").closest("tr.values").find(".new_meta_values").val("");
		}
	});
	$(".new_meta_values").keyup(function(){
		var text = $(this).val();
		$(this).parent("td").closest("tr.values").find(".new_meta_names ").val(text);
	});
	
});	