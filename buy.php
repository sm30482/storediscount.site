<?php
// Check if the userID is provided
if (isset($_GET['userID'])) {
    $userID = htmlspecialchars($_GET['userID']); // Sanitize the userID input
} else {
    die("Error: userID is required.");
}

// Check if the postID is provided, it's optional
if (isset($_GET['postID'])) {
    $postID = '&attributes[postID]=' . urlencode($_GET['postID']);  // Sanitize and encode the postID
} else {
    $postID = ''; // Empty string if no postID is provided
}
if (isset($_GET['postPrice'])) {
    $price = htmlspecialchars($_GET['postPrice']);  // Sanitize and encode the postID
    if($price==17){
        $checkout="https://giorgioricco.myshopify.com/cart/49376234438992:1?attributes[userID]=";
        
    }else{
     $checkout="https://giorgioricco.myshopify.com/cart/49346932638032:1?attributes[userID]=";
    }
} else {
    $price = ''; // Empty string if no postID is provided
}

ob_start();
?>
<style>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Construct the checkout URL with custom attributes
        var checkoutUrl = `<?php echo $checkout.$userID . $postID; ?>`;

        // Open the Shopify checkout in a new window
        $("#open-iframe").on("click", function() {
            var checkoutWindow = window.open(checkoutUrl, 'Checkout', 'width=800,height=800');
            window.parent.postMessage('purchaseStarted', '*');
            // Start checking if the checkout window is closed
            var checkWindowClosed = setInterval(function() {
                if (checkoutWindow.closed) {
                    clearInterval(checkWindowClosed);

                    // After checkout window closes, send a message to the parent window
                    window.parent.postMessage('purchaseComplete', '*');
                }
            }, 1000); // Check every second
        });
    });
</script>

<div class="user-submit" id="open-iframe" processor="https://storediscounts.site/buy.php?userID=<?php echo $userID; ?>&postID=<?php echo urlencode($_GET['postID'] ?? ''); ?>" style="display:inline-block">Unlock · <?php echo $price;?> Tkts</div>

<?php ob_end_flush(); ?>
