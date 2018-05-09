<?php
if (!isset($page_title)) {
    header('Location: error.php');
    exit(1);
}
?>
    </div>
    <!-- /container -->

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>

<!-- Include all compiled plugins (below), or include individual files as needed -->
<!-- Latest compiled and minified JavaScript -->
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.11.0/bootstrap-table.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>

<script type="text/javascript">
	$.fn.select2.defaults.set("theme", "bootstrap");
	var email_reg = <?= tools::$regs['email']; ?>;
	var name_reg = <?= tools::$regs['name']; ?>;
	var validateForm = function() {
		invalid = getInvalid();
		if (invalid.length) {
			$('button[type=submit]').prop('disabled', true);
		} else {
			$('button[type=submit]').prop('disabled', false);
		}
	}
	var getInvalid = function() {
		fields_to_check = {'first_name':name_reg,'last_name':name_reg,'email':email_reg};
		invalid = [];
		$("input:visible:not([type=hidden], [type=submit])")
			.each(function() {
				if(fields_to_check[this.name] && !fields_to_check[this.name].test(this.value)) {
					invalid.push(this.name.replace("_", " "));
				}
			});
		if ($('.ids').length && !$('.ids:checkbox:checked').length) {
			invalid.push("No ID's Selected");
		}
		return invalid;
	}
	var showHideNew = function() {
		if ($('#customer').val()) {
			$('.new-customer').hide();
		} else {
			$('.new-customer').show();
		}
	}
	$(function() {
		$('.select-2').select2();
		showHideNew();
	    validateForm();
	});
	$('#product').on('select2:close', function(e) {
		$('#add-to-cart').focus();
	})
	$('#customer').change(function(){
		showHideNew();
	})
	$('.form-validate').on('submit', function(e) {
		$('.disable-on-submit').prop('disabled', true);
		invalid = getInvalid();
		if (invalid.length) {
			$("#alert-section").html("<div class='alert alert-danger fade in'>Invalid "+invalid.join(', ')+"</div>");
			e.preventDefault();
		}
	})
	$('.form-validate').on('keyup click change', function(e) {
		validateForm();
	})
	var addToCart = function() {
		var alert_type = 'info';
		var product = $('#product option:selected').text();
		var text = "Added "+product+" to <a href='./cart.php'><u>cart</u></a>!";
		var data = {};
		data.id = $('#product').val();
		data.quantity = $('#quantity').val();
		$.post("./add_to_cart.php", data)
		.done(function(data, textStatus, jqXHR) {
			var cart = $(".cart-count");
			var cart_count = parseInt(cart.html());
			cart_count++;
			cart.html(cart_count);
	  	})
	  	.fail(function(jqXHR, textStatus, errorThrown) {
			alert_type = 'danger';
			text = "Failed adding "+product+" to cart!";
	  	})
	  	.always(function() {
			var html = "<div class='alert alert-"+alert_type+" fade in' role='alert'><strong>"+text+"</strong><button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
			$("#alert-section").html(html);
	  	});
	}
	var removeFromCart = function(id) {
		var data = {};
		data.id = id;
		$.post("./remove_from_cart.php", data)
		.done(function(data, textStatus, jqXHR) {
			location.reload();
	  	})
	  	.fail(function(jqXHR, textStatus, errorThrown) {
			$("#alert-section").html("<div class='alert alert-danger fade in'>Failed removing from cart!</div>");
	  		setTimeout(function(){$(".alert").alert('close');}, 2000);
	  	})
	}
</script>
</body>
</html>
