<?php
namespace MRBS;

require "defaultincludes.inc";

$sql =  "SELECT 
    e.id AS entry_id,
    creator.display_name AS creator_name,
    r.room_name,
    a.area_name,
    e.name,
    e.description,
    CASE 
        WHEN DATE(FROM_UNIXTIME(e.start_time)) = CURDATE() 
            THEN CONCAT(
                DATE_FORMAT(FROM_UNIXTIME(e.start_time), '%h:%i %p'),
                ' to ',
                DATE_FORMAT(FROM_UNIXTIME(e.end_time), '%h:%i %p')
            )
        ELSE CONCAT(
                DATE_FORMAT(FROM_UNIXTIME(e.start_time), '%Y-%m-%d %h:%i %p'),
                ' to ',
                DATE_FORMAT(FROM_UNIXTIME(e.end_time), '%h:%i %p')
            )
    END AS reservation_time,
    p.participants,
    g.guest_participants,
    CASE 
        WHEN DATE(FROM_UNIXTIME(e.start_time)) = CURDATE() THEN 'today_reservation'
        ELSE 'upcoming_reservation'
    END AS reservation_group

  FROM mrbs_entry AS e

  JOIN mrbs_users AS creator ON creator.name = e.create_by
  JOIN mrbs_room AS r ON e.room_id = r.id
  JOIN mrbs_area AS a ON r.area_id = a.id

  -- INNER JOIN: only include entries with participants
  JOIN (
      SELECT 
          entry_id,
          GROUP_CONCAT(DISTINCT u.display_name ORDER BY u.display_name SEPARATOR ', ') AS participants
      FROM mrbs_groups AS mg
      JOIN mrbs_users AS u ON LOWER(TRIM(u.email)) = LOWER(TRIM(mg.email))
      WHERE mg.email IS NOT NULL AND mg.email != ''
      GROUP BY mg.entry_id
  ) p ON p.entry_id = e.id

  -- INNER JOIN: only include entries with guests
  JOIN (
      SELECT 
          entry_id,
          GROUP_CONCAT(DISTINCT full_name ORDER BY full_name SEPARATOR ', ') AS guest_participants
      FROM mrbs_groups
      WHERE (email IS NULL OR email = '') AND full_name IS NOT NULL AND full_name != ''
      GROUP BY entry_id
  ) g ON g.entry_id = e.id

  WHERE e.entry_type = 0 
    AND DATE(FROM_UNIXTIME(e.start_time)) >= CURDATE()

  ORDER BY reservation_group, e.start_time;
" ;

$res = db()->sql_query($sql);
$today = [];
$tomorrow = [];
$doneMeeting = [];

$currentTime = time();

foreach ($res as $row) {

    // Parse reservation_time to get start and end timestamps
    // Example: "07:00 AM to 07:45 AM" OR "2025-08-01 07:00 AM to 07:30 AM"

    $timeRange = $row['reservation_time'] ?? '';
    


    // Split by ' to ' or ' to ' with spaces trimmed
    $times = preg_split('/\s*to\s*/i', $timeRange);

    if (count($times) !== 2) {
        // Invalid format, skip or handle gracefully
        continue;
    }


    $startStr = $times[0];  // e.g. "07:00 AM" or "2025-08-01 07:00 AM"
    $endStr = $times[1];    // e.g. "07:45 AM" or "2025-08-01 07:30 AM"

    $status_reservation = $row['reservation_group'] ?? 'upcoming_reservation';

    // Build full datetime strings if missing date for today_reservation

    if ($status_reservation === 'today_reservation') {
        // Add today's date prefix to time-only strings
        $todayDate = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $startStr)) {
            $startStr = $todayDate . ' ' . $startStr;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $endStr)) {
            $endStr = $todayDate . ' ' . $endStr;
        }
    }



    $startTime = strtotime($startStr);
    $endTime = strtotime($endStr);

    // Participants parsing
    $participants = array_filter(array_map('trim', explode(',', $row['participants'] ?? '')));

    // Determine meeting status
    $status_meeting = 'upcoming'; // default

    if ($status_reservation === 'today_reservation') {
          // echo $status_reservation, $currentTime , $endTime;
        if ($currentTime > $endTime) {
          // echo $row['entry_id'];
            $status_meeting = 'done';
        } elseif ($currentTime >= $startTime && $currentTime <= $endTime) {
            $status = 'inprogress';
        } else {
            $status_meeting = 'upcoming';
        }
    } else {
        $status_meeting = 'upcoming';  // future meetings always upcoming
    }



    $dateStr = ($status_reservation !== 'today_reservation') ? date('M d, Y', $startTime) : '';

    $entry = [
        'guestName' => $row['guest_participants'] ?? '',
        'meetingTitle' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'creator' => $row['creator_name'] ?? '',
        'status' => $status_meeting,
        'date' => $dateStr,
        'time' => $timeRange,
        'room' => $row['room_name'] .'-'. $row['area_name'] ?? 'No room yet',
        'participants' => array_values($participants),
    ];

    if ($status_meeting === 'done' && $status_reservation === 'today_reservation') {
        $doneMeeting[] = $entry;
    } elseif ($status_reservation === 'today_reservation' && $status_meeting !== 'done') {
        $today[] = $entry;
    } else {
        $tomorrow[] = $entry;
    }
}
?>


<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Meeting Schedule</title>
  <link rel="stylesheet" href="./public/css/tailwind.min.css" />
  <!-- <script src="https://cdn.tailwindcss.com"></script> -->
  <style>
    /* Carousel container with dynamic height and smooth transition */
    .carousel-container {
      position: relative;
      overflow: hidden;
      transition: height 0.6s ease;
      min-height: 397px; /* minimum 1 row height */
    }

    /* Fade slides stacked absolutely */
    .fade {
      position: absolute;
      inset: 0; /* top:0; right:0; bottom:0; left:0; */
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.8s ease-in-out;
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 8px; /* 0.5rem */
    }

    .fade.active {
      opacity: 1;
      pointer-events: auto;
      position: relative; /* bring active slide in flow */
    }

    .meeting-card {
      width: 100% !important;
      max-width: 1069px;
      height: 390px !important;
      display: flex;
      border-radius: 20px;
      padding-right: 38px;
      overflow: hidden;
      border: 1px solid #cdcdcd;
      box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
      background: white;
      margin: auto;
    }

    /* Left side image with overlay for text */
    .meeting-card .left-side {
      position: relative;
      width: 577px;
      height: 393px;
      flex-shrink: 0;
      background: url('images/rectangle-2.svg') no-repeat center/cover;
      border-top-left-radius: 20px;
      border-bottom-left-radius: 20px;
      color: white;
      padding: 32px 40px; /* 2rem 2.5rem */
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      user-select: none;
      text-shadow: 0 0 6px rgba(0,0,0,0.7);
      padding-right: 38px;
    }
    .meeting-card .guest-name {
      font-size: 2.8rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
      flex-shrink: 0;

      /* Allow wrapping - no truncation */
      white-space: normal;
      overflow: visible;
      text-overflow: unset;
    }
    .meeting-card .meeting-title {
      font-size: 30.4px; /* 1.9rem */
      font-weight: 700;
      margin-bottom: 16px; /* 1rem */
      flex-shrink: 0;
    }
    .meeting-card .description {
      font-size: 24px; /* 1.5rem */
      font-weight: 500;
      line-height: 1.4;
      margin-bottom: 32px; /* 2rem */
      flex-grow: 1;
      overflow-wrap: break-word;
    }
    .meeting-card .creator {
      font-size: 24px; /* 1.5rem */
      font-style: italic;
      opacity: 0.99;
      flex-shrink: 0;
    }

    /* Right side white background with details */
    .meeting-card .right-side {
      position: relative;
      padding: 70px 20px 20px 20px; /* 2rem 3rem */
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      color: #000000ff;
      user-select: none;
    }

    /* Status badges */
    .status-badge {
      position: absolute;
      top: 16px; /* 1rem */
      right: 16px; /* 1rem */
      font-weight: 600;
      padding: 4.8px 20px; /* 0.3rem 1.25rem */
      border-radius: 9999px;
      color: white;
      font-size: 25px; /* 1.25rem */
      user-select: none;
      margin-bottom: 10;
    }
    .status-upcoming {
      background-color: #dc2626;
    }
    .status-inprogress {
      background-color: #ca8a04;
    }
    .status-done {
      background-color: #16a34a;
    }

    /* Info rows with icon and text */
    .info-row {
      display: flex;
      align-items: center;
      gap: 12px; /* 0.75rem */
      font-size: 30.4px; /* 1.4rem */
    }
    .info-row svg {
      flex-shrink: 0;
      width: 30px;
      height: 30px;
      stroke: #6b7280;
      stroke-width: 1.5;
    }

        /* 2x grid layout */
    @media screen and (min-width: 1840px) and (max-width: 2729px) {
      .fade.custom-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-auto-rows: 397px;
      }

      .fade.active.custom-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .meeting-card {
        margin: auto;
      }
    }

    /* Large screen overrides */
    @media (min-width: 2730px) {
      .fade.custom-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-auto-rows: 397px;
      }
      .fade.active.custom-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
      .meeting-card {
        margin: 0;
      }
      .meeting-card .left-side {
        padding: 16px 16px; /* 1rem 1rem */
        padding-right: 38px;
      }
      .meeting-card .guest-name {
        font-size: 44.8px; /* 2.8rem */
        margin-bottom: 6px;
      }
      .meeting-card .meeting-title {
        font-size: 44.8px; /* 2.8rem */
        margin-bottom: 6px;
      }
      .meeting-card .description {
        font-size: 28.8px; /* 1.8rem */
        margin-bottom: 48px; /* 3rem */
      }
      .meeting-card .creator {
        font-size: 24px; /* 1.5rem */
      }
      .info-row {
        font-size: 25.6px; /* 1.6rem */
        margin-bottom: 24px; /* 1.5rem */
      }
      .info-row svg {
        width: 26px;
        height: 26px;
      }
    }

    /* Week container horizontal layout */
    #weeks-container {
      display: flex;
      gap: 12px; /* 0.75rem */
      justify-content: center;
      flex-wrap: nowrap;
      margin-bottom: 16px; /* 1rem */
    }

    .highlighted {
      background-color: #1976D2;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.3s ease;
    }

    .week-item {
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      user-select: none;
      background-color: #e5e7eb;
      color: #374151;
      transition: background-color 0.3s ease;
    }

    .week-item:hover:not(.highlighted) {
      background-color: #d1d5db;
    }

    #weeks-container .week-item {
      padding: 0.75rem 1.5rem; /* comfortable padding */
      border-radius: 25px;    /* your custom radius */
      font-weight: 600;
      background-color: #e5e7eb;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.3s ease;
    }

    #weeks-container .week-item:hover {
      background-color: #d1d5db;
    }

    #weeks-container .highlighted {
      background-color: #1976D2;
      color: white;
      border-radius: 25px; 
    }

        /* custom.css */
    .max-w-\[3780px\] {
      max-width: 3780px;
    }

    .min-h-\[410px\] {
      min-height: 410px;
    }

    .section-label {
      margin-bottom: 16px;
      margin-top: 32px;
    }

    .logo-container {
      max-width: 3780px;
      margin: 0 auto;
    }

  .max-w-300px { max-width: 300px; }
  .max-w-400px { max-width: 400px; }
  .max-w-600px { max-width: 600px; }
  .max-w-800px { max-width: 800px; }
  .max-w-1000px { max-width: 1000px; }

  /* Media queries for responsiveness */

  @media (min-width: 640px) { /* sm */
    .sm\:max-w-400px { max-width: 400px; }
  }

  @media (min-width: 768px) { /* md */
    .md\:max-w-600px { max-width: 600px; }
  }

  @media (min-width: 1024px) { /* lg */
    .lg\:max-w-800px { max-width: 800px; }
  }

  @media (min-width: 1280px) { /* xl */
    .xl\:max-w-1000px { max-width: 1000px; }
  }

  </style>
</head>

<body class="bg-gray-100 font-sans">

  <div class=" max-w-[3780px] mx-auto p-4">
      <div class="bg-white shadow-md  top-0 z-50">
        <div class="max-w-[3780px] mx-auto p-4 flex justify-between items-center">
          
          <!-- Logo -->
          <img
            src="images/logo.svg"
            alt="Company Logo"
            class="w-auto max-w-3780px sm:max-w-400px md:max-w-600px lg:max-w-800px xl:max-w-1000px h-auto"
          />
          
          <!-- Date and Time -->
          <div class="flex flex-col items-center text-center">
            <p id="date" class="text-3xl sm:text-4xl lg:text-6xl font-light text-black mb-1">Loading date...</p>
            <p id="clock" class="text-4xl sm:text-5xl lg:text-7xl font-semibold text-black">--:--:--</p>
          </div>
        </div>
      </div>

    <!-- Header -->
    <header class="mb-8">
      <div class="flex justify-between items-start flex-wrap">
        <div class="flex-1 text-center">
          <p class="text-4xl sm:text-5xl lg:text-7xl font-semibold text-gray-600 mb-6">Reservation for</p>
          <h1 class="text-4xl sm:text-5xl lg:text-7xl font-semibold text-black mb-8">August</h1>

          <!-- Week Selector -->
          <div class="flex justify-center">
            <div id="weeks-container" class="flex flex-wrap justify-center gap-3 max-w-full px-4 sm:px-0 text-5xl"></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Week Selector -->
    <div class="flex justify-center space-x-4 mb-8">
      <div id="weeks-container"></div>
    </div>

    <!-- Today Section -->
    <h2 class="text-4xl sm:text-5xl lg:text-7xl font-semibold">Today</h2>
    <div class="relative min-h-[410] section-label">
      <div id="today-carousel" class="carousel-container relative"></div>
    </div>

    <!-- Tomorrow Section -->
    <h2 class="text-4xl sm:text-5xl lg:text-7xl font-semibold">Upcoming</h2>
    <div class="relative min-h-[410] section-label">
      <div id="tomorrow-carousel" class="carousel-container relative"></div>
    </div>

    <!-- Done Section -->
    <h2 class="text-4xl sm:text-5xl lg:text-7xl font-semibold">Done</h2>
    <div class="relative min-h-[410] section-label">
      <div id="done-carousel" class="carousel-container relative"></div>
    </div>

  </div>



<script>
document.addEventListener('DOMContentLoaded', () => {
  const dateEl = document.getElementById('date');
  const clockEl = document.getElementById('clock');

  function updateDate() {
    const now = new Date();
    const options = { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' };
    dateEl.textContent = now.toLocaleDateString('en-US', options);
  }

  function updateClock() {
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString([], { 
      hour: '2-digit', 
      minute: '2-digit', 
      second: '2-digit'  
    });
  }

  // Update immediately
  updateDate();
  updateClock();

  // Keep clock ticking
  setInterval(updateClock, 1000);
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- Icon SVGs ---
  const icons = {
    date: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect width="18" height="16" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
    time: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9"/><polyline points="12 6 12 12 16 14"/></svg>`,
    room: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="7" width="18" height="10" rx="2" ry="2"/><line x1="16" y1="7" x2="16" y2="17"/><line x1="8" y1="7" x2="8" y2="17"/></svg>`,
    participants: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="9" cy="7" r="4"/><circle cx="17" cy="7" r="4"/><path d="M1 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/></svg>`
  };

  // --- Text truncation helpers ---
  function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.slice(0, maxLength - 3) + '...';
  }
  const maxDescriptionLength = 70;
  const fullNameLength = 20;

  // --- Meeting card HTML template ---
  function meetingCardHTML({
    guestName,
    meetingTitle = "Meeting Title",
    description,
    creator = "Creator Name",
    status,
    date,
    time,
    room,
    participants = [],
  }) {
    let statusText = '';
    let statusClass = '';
    if(status === 'upcoming') {
      statusText = 'Upcoming';
      statusClass = 'status-badge status-upcoming';
    } else if(status === 'inprogress') {
      statusText = 'In Progress';
      statusClass = 'status-badge status-inprogress';
    } else if(status === 'done') {
      statusText = 'Complete';
      statusClass = 'status-badge status-done';
    }

    return `
      <article class="meeting-card">
        <div class="left-side">
          <div class="guest-name">${truncateText(guestName, fullNameLength)}</div>
          <div class="meeting-title">${meetingTitle}</div>
          <div class="description">${truncateText(description, maxDescriptionLength)}</div>
          <div class="creator">Organizer: ${creator}</div>
        </div>
        <div class="right-side">
          <div class="${statusClass}">${statusText}</div>
          ${date ? `<div class="info-row">${icons.date}<span>${date}</span></div>` : ''}
          <div class="info-row">${icons.time}<span>${time}</span></div>
          <div class="info-row">${icons.room}<span>${room}</span></div>
          <div class="info-row">${icons.participants}<span>${formatParticipants(participants)}</span></div>
        </div>
      </article>
    `;
  }

  // --- Participants formatting based on screen width ---
  function formatParticipants(participants) {
    if (!Array.isArray(participants)) return '';
    const width = window.innerWidth;
    let displayCount;

    if (width >= 1840 && width <= 2729) {
      displayCount = 2;
    } else if (width > 2729) {
      displayCount = 4;
    } else {
      displayCount = participants.length;
    }

    if (participants.length > displayCount) {
      const shown = participants.slice(0, displayCount).join(', ');
      const remaining = participants.length - displayCount;
      return `${shown} +${remaining}`;
    }

    return participants.join(', ');
  }

  // --- Carousel layout helpers ---
  function getColumnsCount() {
    const width = window.innerWidth;
    if (width >= 2730) return 3;
    if (width >= 1840) return 2;
    return 1;
  }

  function getRowsCount(container) {
    if (!container) return 0;

    if (container.id === 'done-carousel') {
      return 1;
    }

    const width = window.innerWidth;
    if (width >= 2730 && container.id === 'today-carousel') {
      return 2;
    }
    if (width >= 2730) return 3;
    return 2;
  }

  function getCardsPerPage(container) {
    return getColumnsCount() * getRowsCount(container);
  }

  function calculateHeight(cardsCount, container) {
    const columns = getColumnsCount();
    const fixedRows = getRowsCount(container);
    const rows = Math.min(Math.ceil(cardsCount / columns), fixedRows);

    const rowHeight = 397; // px card height
    const gap = 16; // px gap between rows

    return rows * rowHeight + (rows - 1) * gap;
  }

  // --- Chunk array helper ---
  function chunkArray(arr, n) {
    if (!Array.isArray(arr)) {
      console.error('chunkArray expects an array but got:', arr);
      return [];
    }
    const chunks = [];
    for(let i = 0; i < arr.length; i += n) {
      chunks.push(arr.slice(i, i + n));
    }
    return chunks;
  }

  // --- Carousel render function ---
  function renderCarousel(container, meetings) {
    if (!container) return;

    if (!Array.isArray(meetings)) {
      console.error('renderCarousel expected an array for meetings');
      meetings = []; // fallback to empty array
    }

    container.meetingsData = meetings;
    if (meetings.length === 0) {
      // Show "No cards yet" placeholder
      container.innerHTML = `
        <div class="flex items-center justify-center h-64 border-2 border-dashed border-gray-600 p-4">
          <p class="text-8xl">No cards yet</p>
        </div>
      `;
      container.style.height = '100px'; // set a fixed height for the placeholder
      return;
    }
    const cardsPerPage = getCardsPerPage(container);
    const pages = chunkArray(meetings, cardsPerPage);
    if (pages.length === 0) {
      container.style.height = '0px';
      return; // no pages, nothing to render
    }
    container.innerHTML = '';

    pages.forEach((pageData, idx) => {
      const slide = document.createElement('section');
      slide.className = 'fade custom-grid';
      if (idx === 0) slide.classList.add('active');

      pageData.forEach(data => {
        const tempTemplate = document.createElement('template');
        tempTemplate.innerHTML = meetingCardHTML(data).trim();
        slide.appendChild(tempTemplate.content.firstElementChild);
      });

      container.appendChild(slide);
    });

    if (pages.length <= 1) {
      container.style.height = calculateHeight(pages[0].length, container) + 'px';
      return;
    }

    let currentIndex = 0;
    container.style.height = calculateHeight(pages[0].length, container) + 'px';

    container.carouselIntervalId = setInterval(() => {
      const slides = container.querySelectorAll('.fade');
      slides[currentIndex].classList.remove('active');
      const nextIndex = (currentIndex + 1) % slides.length;
      slides[nextIndex].classList.add('active');

      const currentCardsCount = pages[currentIndex].length;
      const nextCardsCount = pages[nextIndex].length;

      const currentHeight = calculateHeight(currentCardsCount, container);
      const nextHeight = calculateHeight(nextCardsCount, container);

      container.style.height = nextHeight < currentHeight ? nextHeight + 'px' : currentHeight + 'px';

      currentIndex = nextIndex;
    }, 25000);
  }

  // --- PHP injected data with fallback to empty arrays ---
  let doneMeetingsData = <?php echo json_encode($doneMeeting ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  let tomorrowMeetingsData = <?php echo json_encode($tomorrow ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  let todayMeetingsData = <?php echo json_encode($today ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  // --- Container references ---
  const todayContainer = document.getElementById('today-carousel');
  const tomorrowContainer = document.getElementById('tomorrow-carousel');
  const doneContainer = document.getElementById('done-carousel');

  // --- Initial render ---
  renderCarousel(todayContainer, todayMeetingsData);
  renderCarousel(tomorrowContainer, tomorrowMeetingsData);
  renderCarousel(doneContainer, doneMeetingsData);

  // --- Adjust height on resize ---
  window.addEventListener('resize', () => {
    [todayContainer, tomorrowContainer, doneContainer].forEach(container => {
      if (!container) return;
      const activeSlide = container.querySelector('.fade.active');
      if (!activeSlide) return;
      const cardsCount = activeSlide.children.length;
      container.style.height = calculateHeight(cardsCount, container) + 'px';
    });
  });

  // --- Auto-refresh with fetch ---
  let currentData = { today: [], tomorrow: [], done: [] };
  let lastETag = null;
  let fetchInProgress = false;

  function isSameData(newData, oldData) {
    return JSON.stringify(newData) === JSON.stringify(oldData);
  }

  function fetchMeetings() {
    if (fetchInProgress) return;
    fetchInProgress = true;

    fetch('fetching_guest_booking.php', { headers: lastETag ? { 'If-None-Match': lastETag } : {} })
      .then(res => {
        fetchInProgress = false;
        if (res.status === 304) return null;
        lastETag = res.headers.get('ETag');
        return res.json();
      })
      .then(data => {
        if (!data) return;

        if (!isSameData(data.today, currentData.today)) {
          renderCarousel(todayContainer, data.today);
          currentData.today = data.today;
        }
        if (!isSameData(data.tomorrow, currentData.tomorrow)) {
          renderCarousel(tomorrowContainer, data.tomorrow);
          currentData.tomorrow = data.tomorrow;
        }
        if (!isSameData(data.done, currentData.done)) {
          renderCarousel(doneContainer, data.done);
          currentData.done = data.done;
        }
      })
      .catch(err => {
        fetchInProgress = false;
        console.error('Error fetching meetings:', err);
      });
  }

  // --- Start polling ---
  fetchMeetings();
  setInterval(fetchMeetings, 5000);

});
</script>

</body>
</html>
