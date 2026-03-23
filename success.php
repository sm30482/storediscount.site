<?php
$variantTicketMap = [
    '51688630452560' => 3,  // 3 tickets bundle
    '51688630485328' => 6,  // 6 tickets bundle
    '51688630518096' => 10  // 10 tickets bundle
];
// Your Shopify webhook secret key (found in your Shopify Admin under webhook settings)
$shopify_secret = 'db2a113f87206cef821c40588c6ff087b7dca16ebb34162f9ce3ef8976d78884';

// Retrieve the HMAC header sent by Shopify
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];

// Read the raw POST data sent by Shopify
$input = file_get_contents('php://input');

// Log the raw input
file_put_contents('hmac_debug.txt', "Raw Input: $input\n", FILE_APPEND);

// Calculate the HMAC based on the raw input and secret
$calculated_hmac = base64_encode(hash_hmac('sha256', $input, $shopify_secret, true));

// Log the calculated HMAC and the Shopify HMAC header for comparison
file_put_contents('hmac_debug.txt', "Shopify HMAC: $hmac_header\n", FILE_APPEND);
file_put_contents('hmac_debug.txt', "Calculated HMAC: $calculated_hmac\n", FILE_APPEND);

if (hash_equals($hmac_header, $calculated_hmac)) {
    // The request is from Shopify, proceed with processing
    file_put_contents('hmac_debug.txt', "HMAC Validation: Success\n", FILE_APPEND);
    $data = json_decode($input, true);

    if (isset($data['id'])) {
        
        // Extract the custom attributes from the order line items
        $note_attributes = $data['note_attributes'];
        $userID = $postID = null;
        $itemPrice = $data['line_items'][0]['price'];
        
        foreach ($note_attributes as $attribute) {
            if ($attribute['name'] == 'userID') {
                $userID = $attribute['value'];
            }
            if ($attribute['name'] == 'postID') {
                $postID = $attribute['value'];
            }
        }

        // Log userID and postID
        file_put_contents('hmac_debug.txt', "userID: $userID, postID: $postID\n", FILE_APPEND);

        if ($userID) {
            // Connect to the database
            include 'db_connection.php';
            $conn = db_connect();

            // Check if the database connection was successful
            if ($conn->connect_error) {
                file_put_contents('hmac_debug.txt', "DB Connection Error: " . $conn->connect_error . "\n", FILE_APPEND);
                exit("Database connection failed");
            }

            // Get the quantity of products purchased
            $line_items = $data['line_items'];
            $quantity = 0;
            foreach ($line_items as $item) {
                $variant_id = $item['variant_id'];
                $quantity = $item['quantity'];  // Sum up all the quantities
                  if (isset($variantTicketMap[$variant_id])) {
                    $itemPrice = 14;
                    $quantity = $variantTicketMap[$variant_id];
                } else {
                    file_put_contents('hmac_debug.txt', "Unknown variant: $variant_id\n", FILE_APPEND);
                }
            }

            // Log the quantity of items bought
            file_put_contents('hmac_debug.txt', "Quantity: $quantity\n", FILE_APPEND);

            // Select the corresponding number of codes from the tickets table (ticketUser is NULL)
            $codes = [];
            $codeQuery = "SELECT ticketID, ticketValue FROM tickets WHERE ticketUser IS NULL LIMIT ?";
            $stmt = $conn->prepare($codeQuery);

            if ($stmt === false) {
                file_put_contents('hmac_debug.txt', "Error preparing codeQuery: " . $conn->error . "\n", FILE_APPEND);
                exit("Error preparing the SQL query.");
            }

            $stmt->bind_param("i", $quantity);
            $stmt->execute();
            $stmt->bind_result($ticketID, $ticketValue);

            while ($stmt->fetch()) {
                $codes[] = $ticketValue;
            }

            // Close the select query before running the update
            $stmt->close();

            // Now, update each selected ticket
            foreach ($codes as $ticket) {
                $updateQuery = "UPDATE tickets SET ticketUser = ?, ticketDate = ? WHERE ticketValue = ?";
                $updateStmt = $conn->prepare($updateQuery);

                if ($updateStmt === false) {
                    file_put_contents('hmac_debug.txt', "Error preparing updateQuery: " . $conn->error . "\n", FILE_APPEND);
                    exit("Error preparing the SQL update query.");
                }

                $currentDate = date('Y-m-d H:i:s');
                $updateStmt->bind_param('sss', $userID, $currentDate, $ticket);

                if (!$updateStmt->execute()) {
                    file_put_contents('hmac_debug.txt', "Error updating ticketValue $ticket: " . $updateStmt->error . "\n", FILE_APPEND);
                } else {
                    file_put_contents('hmac_debug.txt', "Successfully updated ticketValue $ticket\n", FILE_APPEND);
                }
                
                // Close the update statement after executing it
                $updateStmt->close();
            }

            // Log the selected codes
            file_put_contents('hmac_debug.txt', "Selected codes: " . print_r($codes, true) . "\n", FILE_APPEND);

            // success.php (add this before sending the POST request to handlebuy.php)
            $secret_token = '79a0f3827ef97ebdef591918448dc7d3471b49235664e26235f184b413bc885c'; // This should be a complex and unique value
            $postData = [
                'userID' => $userID,
                'postID' => $postID,
                'price'  => $itemPrice,
                'codes'  => $codes,
                'token'  => $secret_token // Add the secret token to the POST data
            ];

            $ch = curl_init('https://giorgiobts.com/php/handlebuy.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            // Log the response from handelbuy.php
            file_put_contents('hmac_debug.txt', "Response from handelbuy.php: $response\n", FILE_APPEND);

            // Redirect to a success page or handle the response
            header("Location: https://storediscounts.site/success.php?userID={$userID}&postID={$postID}");
            exit();
        } else {
            file_put_contents('hmac_debug.txt', "userID or postID not found in the order.\n", FILE_APPEND);
        }
    } else {
        file_put_contents('hmac_debug.txt', "Invalid webhook data.\n", FILE_APPEND);
    }
} else {
    http_response_code(403);
    echo "Forbidden: Invalid HMAC signature.";

    file_put_contents('hmac_debug.txt', "HMAC Validation: Failed\n", FILE_APPEND);
    exit();
}
?>
