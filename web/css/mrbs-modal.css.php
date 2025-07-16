<?php
// mrbs-modal.css.php

header("Content-Type: text/css"); // This tells the browser that the file is CSS

// Add any dynamic PHP code for styles here if needed
?>

/* Modal Styles */
.booking-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.booking-modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%;
    max-width: 1200px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    max-height: 80%;
    overflow-y: auto;
}

/* Header Styles */
.booking-modal-header {
    background-color: #1976D2;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1001;
}

.booking-modal-header h1 {
    margin: 0;
    font-size: 24px;
    color: #fff;
}

.booking-modal-header input {
    width: 70%;
    padding: 10px;
    font-size: 16px;
    margin-right: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.booking-modal-header .close-btn {
    background-color: #ff5c5c;
    color: white;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    font-size: 16px;
    border-radius: 5px;
}

.booking-modal-header .close-btn:hover {
    background-color: #ff1c1c;
}

/* Content Styles */
.booking-details-content {
    padding: 20px;
}

.table-container {
    overflow-x: auto;
    border: 1px solid #ccc;
    border-radius: 10px;
    padding: 10px;
    margin: 0 auto;
    width: 100%;
}

.table {
    width: 100%;
    text-align: center;
    border-collapse: collapse;
    margin-top: 20px;
}

.table th, .table td {
    border: 1px solid #ddd;
    padding: 8px;
}

.table th {
    background-color: #1976D2;
    font-size: 16px;
    color: #fff;
}

.table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.table tbody tr:hover {
    background-color: #f1f1f1;
}

.table tbody td {
    font-size: 14px;
    color: #555;
}
