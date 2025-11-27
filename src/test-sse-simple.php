<?php
/**
 * Simple SSE Test - No dependencies
 * This will help us determine if SSE works at all on your server
 */

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Function to send SSE message
function sendMessage($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Send 10 test messages
for ($i = 1; $i <= 10; $i++) {
    sendMessage('test', [
        'number' => $i,
        'message' => "Test message $i of 10",
        'timestamp' => date('H:i:s')
    ]);
    
    // Log to file
    file_put_contents('sse_test.log', date('Y-m-d H:i:s') . " - Sent message $i\n", FILE_APPEND);
    
    sleep(1); // 1 second delay
}

// Send completion
sendMessage('complete', [
    'message' => 'Test complete! SSE is working on your server.',
    'timestamp' => date('H:i:s')
]);

file_put_contents('sse_test.log', date('Y-m-d H:i:s') . " - Test completed successfully\n", FILE_APPEND);
?>