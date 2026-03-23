<?php 
// Check if the userID is provided
if (isset($_GET['userID'])) {
    $userID = htmlspecialchars($_GET['userID']); // Sanitize the userID input
} else {
    die("Error: userID is required.");
}
?>
<link href="https://fonts.googleapis.com/css2?family=Assistant:wght@200..800&amp;display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Include jQuery -->  
<script src="https://sdks.shopifycdn.com/js-buy-sdk/v2/latest/index.umd.min.js"></script>
 <style>
    body{
        font-family: Assistant, sans-serif;
        margin: 0!important;
    }
    ul{
        margin: 0;
        padding-inline-start: 20px;
        color: #0771a5;
    }
    .ticket-option {
        margin: 15px 0;
        cursor: pointer;
    }
    .main-option-side{
        text-align: left;
        font-size: x-large;
        width: 92px;
        display: inline-block;
        border-right: 2px dotted #ddd;
        color: #0771a5;
        line-height:   36px;
        height: 68px;
        padding-bottom: 4px;
    }
    .main-option-side>span>strong {
        font-size:  30px;
    }
    .medium-option-side{
        text-align: left;
        display: inline-block;
        border-right: 2px dotted #ddd;
        height: 72px;
        width: 123px;
    }
    .medium-option-side>ul>li>span>strong{
        font-size:  19px;
    }

    .price-option-side{
        text-align: right;
        display: inline-block;
        width: 82px;
        font-size: 47px;
        height: 68px;
        vertical-align: top;
        line-height: 62px;
        color:#0771a5;
    }
    .option-box {
        border: 2px solid #ddd;
        padding: 10px;        
        border-radius: 8px;
        background-color: #fffcf5;
        width: 315px;
        margin: 0 auto;
        text-align: center;
        transition: border-color 0.3s ease, background-color 0.3s ease;
    }

    .ticket-option.selected .option-box {
        border-color: #ffc570;
        background-color: #f8ffb9;
    }

    .strikethrough {
        text-decoration: line-through;
        color: #ff3285;
        font-style: italic;
    }

 .user-submit {
        font-family: Assistant, sans-serif;
        font-weight: 400;
        display: inline-block;
        padding: 0 20px;
        cursor: pointer;
        line-height: 45px;
        background-color: #12729e;
        height: 45px;
        border-radius: 10px;
        text-align: center;
        font-size: x-large;
        color: #e4f6fa;
        margin: 0px;
        border: 1px solid #0269aa;
    }
    .user-submit:hover {
        background-color: #63b9e9;
        border: 1px solid #c7d8ec;
        color: white;
    }

</style>
<body>
<form id="ticketBundleForm" action="" method="post" style="text-align:center;">
        

                <div class="ticket-option" data-value="3">
                    <div class="option-box">
                        <div class="main-option-side">
                            <span>Buy<strong> 42</strong></span>
                            <span>Tickets<strong></strong></span>
                        </div>
                        <div class="medium-option-side" option side> 
                            <ul>
                                <li><span class="strikethrough"><strong>€42</strong></span></li>
                                <li><span style=""><strong>15%</strong> discount</span></li>
                                <li><span style="">Save <strong>€6</strong></span></li>
                            </ul>
                        </div>
                        <div class="price-option-side">
                            <span><strong>€36</strong></span>
                        </div>                       
                    </div>
                </div>
                
                <div class="ticket-option" data-value="6">
                    <div class="option-box">
                        <div class="main-option-side">
                            <span>Get<strong> 84</strong></span>
                            <span>Tickets<strong></strong></span>
                        </div>
                        <div class="medium-option-side" option side> 
                            <ul>
                                <li><span class="strikethrough"><strong>€84</strong></span></li>
                                <li><span style=""><strong>22%</strong> discount</span></li>
                                <li><span style="">Save <strong>€19</strong></span></li>
                            </ul>
                        </div>
                        <div class="price-option-side">
                            <span><strong>€65</strong></span>
                        </div>                       
                    </div>
                </div>
                
                <div class="ticket-option" data-value="10">
                    <div class="option-box">
                        <div class="main-option-side">
                            <span>Get<strong> 140</strong></span>
                            <span>Tickets<strong></strong></span>
                        </div>
                        <div class="medium-option-side" option side> 
                            <ul>
                                <li><span class="strikethrough"><strong>€140</strong></span></li>
                                <li><span style=""><strong>25%</strong> discount</span></li>
                                <li><span style="">Save <strong>€35</strong></span></li>
                            </ul>
                        </div>
                        <div class="price-option-side">
                            <span><strong>€105</strong></span>
                        </div>                       
                    </div>
                </div>

                <input type="hidden" name="ticket-bundle" id="selectedTicketBundle" value="3-tickets">
                
                
                 
            </form>
<script>
   $(document).ready(function () {
        const variantMap = {
            3: '51688630452560',   // 36 tickets
            6: '51688630485328',   // 72 tickets
            10: '51688630518096'   // 120 tickets
        };

        $('.ticket-option').on('click', function () {
            var $this = $(this);
            $('.ticket-option').removeClass('selected');
            $this.addClass('selected');

            var selectedBundle = parseInt($this.data('value'));
            var variantID = variantMap[selectedBundle];
            var userID = '<?php echo $userID; ?>';

            var checkoutUrl = `https://giorgioricco.myshopify.com/cart/${variantID}:1?attributes[userID]=${userID}`;

    var checkoutWindow = window.open(checkoutUrl, 'checkout', 'width=800,height=800');
    window.parent.postMessage('purchaseStarted', '*');

    var checkWindowClosed = setInterval(function () {
      if (checkoutWindow.closed) {
        clearInterval(checkWindowClosed);
        window.parent.postMessage('purchaseComplete', '*');
      }
    }, 1000);
  });
});
</script>

</body>