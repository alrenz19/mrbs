<?php
    header("Content-Type: text/javascript");
?>
// scripts/modal.js

document.getElementById("viewBookingsBtn").onclick = function() {
    document.getElementById("bookingModal").style.display = "block";
    fetchBookingDetails();
};

function closeModal() {
    document.getElementById("bookingModal").style.display = "none";
}

function fetchBookingDetails() {
    // Make an AJAX request to fetch the booking details
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "booking_details.php", true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var bookingDetailsContent = document.getElementById("bookingDetailsContent");
            if (bookingDetailsContent) {
                bookingDetailsContent.innerHTML = xhr.responseText;
            }
        } else {
            document.getElementById("bookingDetailsContent").innerHTML = "<p>Error loading booking details.</p>";
        }
    };
    xhr.send();
}

function searchBookings() {
    const searchBox = document.getElementById('searchBox');
    const searchTerm = searchBox.value.toLowerCase();  // Get the search term in lowercase
    const bookingDetailsContent = document.getElementById('bookingDetailsContent');
    const rows = bookingDetailsContent.getElementsByTagName('tr');  // Get all rows in the table

    // Loop through all rows and hide those that don't match the search term
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const isHeader = row.querySelector('th') !== null; // Check if the row contains table headers
        if (isHeader) {
            // Always show header rows
            row.style.display = '';
            continue;
        }

        let rowText = row.textContent.toLowerCase();  // Get all text from the row in lowercase
        if (rowText.includes(searchTerm)) {
            row.style.display = '';  // Show the row if it matches the search term
        } else {
            row.style.display = 'none';  // Hide the row if it doesn't match
        }
    }
}
 
