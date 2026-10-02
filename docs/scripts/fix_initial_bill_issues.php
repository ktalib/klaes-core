<?php
/**
 * Fix INITIAL BILL date fields and receipt number issues
 */

echo "Fixing INITIAL BILL Issues\n";
echo "=========================\n\n";

$filePath = __DIR__ . '/resources/views/primaryform/partials/initial_bill.blade.php';

if (!file_exists($filePath)) {
    echo "❌ File not found: $filePath\n";
    exit(1);
}

// Read the file content
$content = file_get_contents($filePath);

// Issue 1: Remove pre-filled date values (value="2025-10-10")
echo "1. Fixing date field values...\n";
$originalContent = $content;

// Replace all occurrences of value="2025-10-10" with empty value
$content = str_replace('value="2025-10-10"', '', $content);

$dateFixCount = substr_count($originalContent, 'value="2025-10-10"');
echo "   ✅ Removed $dateFixCount pre-filled date values\n";
echo "   → Date fields now allow user selection\n\n";

// Issue 2: Add input restrictions to prevent currency symbols in receipt fields
echo "2. Adding input restrictions for receipt number fields...\n";

// Find receipt number input fields and add input restrictions
$receiptPattern = '/(<input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="[^"]*receipt_number")/';

$content = preg_replace(
    $receiptPattern,
    '$1 oninput="this.value = this.value.replace(/[₦$€£¥]/g, \'\')"',
    $content
);

$receiptFixCount = preg_match_all($receiptPattern, $originalContent);
echo "   ✅ Added currency symbol prevention to receipt number fields\n";
echo "   → Receipt numbers now reject currency symbols on input\n\n";

// Write the updated content back to the file
if (file_put_contents($filePath, $content) !== false) {
    echo "✅ Successfully updated initial_bill.blade.php\n\n";
    
    echo "SUMMARY OF FIXES:\n";
    echo "-----------------\n";
    echo "✅ Date Selection Fix: Removed {$dateFixCount} pre-filled date values\n";
    echo "   • Users can now select their own payment dates\n";
    echo "   • Date fields start empty instead of 2025-10-10\n\n";
    
    echo "✅ Receipt Number Fix: Added input validation\n";
    echo "   • Currency symbols (₦$€£¥) are automatically removed on input\n";
    echo "   • Receipt numbers remain as pure text/numbers\n";
    echo "   • Prevents encoding issues like â‚¦ in database\n\n";
    
    echo "NEXT STEPS:\n";
    echo "1. Test form submission with dates and receipt numbers\n";
    echo "2. Verify dates are selectable and receipt numbers don't contain currency symbols\n";
    echo "3. Check database storage is clean\n\n";
    
} else {
    echo "❌ Failed to write updates to file\n";
    exit(1);
}

echo "Initial Bill Issues - FIXED ✅\n";
?>