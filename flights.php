<?php
// Move all includes and processing to the top
include 'includes/db_connect.php';

// Debug: Print POST data
error_log("POST data: " . print_r($_POST, true));

// Handle Delete
if (isset($_POST['delete'])) {
    error_log("Processing delete for flight_id: " . $_POST['flight_id']);
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE flight_id = ?");
    $stmt->execute([$_POST['flight_id']]);
    error_log("Delete successful");
    header("Location: flights.php");
    exit();
}

// Handle Add/Edit
if (isset($_POST['submit'])) {
    error_log("Processing submit form");
    try {
        $pdo->beginTransaction();
        
        $departure_time = $_POST['departure_date'] . ' ' . $_POST['departure_time'];
        $arrival_time = $_POST['arrival_date'] . ' ' . $_POST['arrival_time'];
        
        error_log("Departure time: $departure_time");
        error_log("Arrival time: $arrival_time");
        
        if (isset($_POST['schedule_id'])) {
            // Update
            error_log("Updating schedule_id: " . $_POST['schedule_id']);
            $stmt = $pdo->prepare("
                UPDATE schedules 
                SET flight_id = ?, 
                    departure_time = ?, 
                    arrival_time = ? 
                WHERE schedule_id = ?
            ");
            $stmt->execute([
                $_POST['flight_id'], 
                $departure_time, 
                $arrival_time, 
                $_POST['schedule_id']
            ]);
        } else {
            // Insert - Get the next schedule_id
            error_log("Inserting new schedule");
            $next_id = $pdo->query("SELECT MAX(schedule_id) + 1 FROM schedules")->fetchColumn();
            $next_id = $next_id ?: 1;
            error_log("Generated next_id: $next_id");
            
            $stmt = $pdo->prepare("
                INSERT INTO schedules 
                (schedule_id, flight_id, departure_time, arrival_time) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $next_id,
                $_POST['flight_id'], 
                $departure_time, 
                $arrival_time
            ]);
        }
        
        $pdo->commit();
        error_log("Transaction committed successfully");
        header("Location: flights.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error occurred: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get schedule for editing if ID is provided
$editing = false;
$schedule = null;
if (isset($_GET['edit'])) {
    error_log("Loading schedule for editing: " . $_GET['edit']);
    $editing = true;
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE schedule_id = ?");
    $stmt->execute([$_GET['edit']]);
    $schedule = $stmt->fetch();
    error_log("Loaded schedule data: " . print_r($schedule, true));
}

// Fetch all available flights
error_log("Fetching available flights");
$flights = $pdo->query("
    SELECT DISTINCT s.flight_id, 
           a.model as aircraft_model,
           dep.code as departure_code, 
           arr.code as arrival_code
    FROM schedules s
    JOIN routes r ON s.flight_id = r.route_id
    JOIN airports dep ON r.departure_airport_id = dep.airport_id
    JOIN airports arr ON r.arrival_airport_id = arr.airport_id
    JOIN aircraft a ON a.aircraft_id = s.flight_id
    ORDER BY s.flight_id
")->fetchAll();
error_log("Fetched " . count($flights) . " flights");

// Fetch all schedules
error_log("Fetching all schedules");
$schedules = $pdo->query("
    SELECT s.schedule_id, 
           s.flight_id, 
           s.departure_time, 
           s.arrival_time,
           a.model as aircraft_model,
           dep.code as departure_code, 
           dep.city as departure_city,
           arr.code as arrival_code, 
           arr.city as arrival_city
    FROM schedules s
    JOIN routes r ON s.flight_id = r.route_id
    JOIN airports dep ON r.departure_airport_id = dep.airport_id
    JOIN airports arr ON r.arrival_airport_id = arr.airport_id
    JOIN aircraft a ON a.aircraft_id = s.flight_id
    ORDER BY s.departure_time
")->fetchAll();
error_log("Fetched " . count($schedules) . " schedules");

// Now include the header after all processing
include 'includes/header.php';
?>

<!-- Rest of your HTML code --> 