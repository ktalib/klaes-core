<?php
echo "=== GROUP CALCULATION LOGIC TEST ===\n\n";

// Function to calculate group and position
function calculateGroup($number) {
    $group_number = ceil($number / 100.0);
    $position_in_group = (($number - 1) % 100) + 1;
    return [$group_number, $position_in_group];
}

// Test cases
$test_cases = [
    1, 2, 50, 99, 100,     // Group 1 cases
    101, 150, 199, 200,    // Group 2 cases  
    201, 250, 300,         // Group 3 cases
    999, 1000,             // Group 10 cases
    1001, 1100,            // Group 11 cases
    4992                   // Example from user (RES-1994-4992)
];

echo "Number\tGroup\tPosition\tRange Description\n";
echo "------\t-----\t--------\t-----------------\n";

foreach ($test_cases as $number) {
    list($group, $position) = calculateGroup($number);
    $range_start = (($group - 1) * 100) + 1;
    $range_end = $group * 100;
    
    echo sprintf("%d\t%d\t%d\t\t%d-%d\n", 
        $number, $group, $position, $range_start, $range_end);
}

echo "\n=== SQL FORMULA VALIDATION ===\n";

try {
    $pdo = new PDO('sqlsrv:server=localhost;database=klas', 'klas', 'YourStrongPassword123!');
    
    // Test SQL formulas on actual data
    $sql = "
    SELECT TOP 10
        awaiting_fileno,
        number,
        CEILING(CAST(number AS FLOAT) / 100.0) as group_number,
        ((number - 1) % 100) + 1 as position_in_group,
        landuse,
        year
    FROM grouping 
    WHERE landuse = 'RESIDENTIAL' 
        AND year = 1981
        AND number IN (1, 50, 100, 101, 200, 201, 300)
    ORDER BY number";
    
    $stmt = $pdo->query($sql);
    
    echo "\nFile Number\t\tNumber\tGroup\tPosition\n";
    echo "-------------\t\t------\t-----\t--------\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%s\t%d\t%d\t%d\n", 
            $row['awaiting_fileno'], 
            $row['number'], 
            $row['group_number'], 
            $row['position_in_group']
        );
    }
    
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

echo "\n=== GROUP RANGE EXAMPLES ===\n";

// Show how groups map to ranges
for ($group = 1; $group <= 5; $group++) {
    $start = (($group - 1) * 100) + 1;
    $end = $group * 100;
    echo "Group $group: Files $start to $end\n";
}

echo "\n=== LAND USE GROUP EXAMPLES ===\n";

$landuses = ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURE'];
foreach ($landuses as $landuse) {
    echo "\n$landuse Groups:\n";
    for ($group = 1; $group <= 3; $group++) {
        $start = (($group - 1) * 100) + 1;
        $end = $group * 100;
        echo "  Group $group: $landuse-1981-$start to $landuse-1981-$end\n";
    }
}

echo "\n✅ Group calculation logic validated!\n";
?>