<?php
require_once __DIR__ . '/../config/database.php';

$adminIdStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$adminRow = $adminIdStmt->fetch();
$adminId = $adminRow ? (int) $adminRow['id'] : 1;

$events = [
    [
        'event_name' => 'PTC Basketball Tournament',
        'category_name' => 'Sports Events',
        'venue_name' => 'School Gymnasium',
        'event_date' => '2027-01-18',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'description' => 'Sample campus showcase for student athletes. This demo event is for planning and can be edited by the administrator at any time.',
        'image' => 'assets/images/sports-basketball.jpg',
        'maximum_participants' => 80,
    ],
    [
        'event_name' => 'Volleyball Championship',
        'category_name' => 'Sports Events',
        'venue_name' => 'School Gymnasium',
        'event_date' => '2027-02-06',
        'start_time' => '09:00:00',
        'end_time' => '13:00:00',
        'description' => 'Sample indoor volleyball competition for students and campus teams. This demo event is editable by the administrator and not officially confirmed.',
        'image' => 'assets/images/sports-volleyball.jpg',
        'maximum_participants' => 60,
    ],
    [
        'event_name' => 'Badminton Competition',
        'category_name' => 'Sports Events',
        'venue_name' => 'School Gymnasium',
        'event_date' => '2027-03-11',
        'start_time' => '14:00:00',
        'end_time' => '17:30:00',
        'description' => 'Sample badminton match for students seeking active participation and campus sports engagement. This is a demo event for presentation purposes.',
        'image' => 'assets/images/sports-basketball.jpg',
        'maximum_participants' => 40,
    ],
    [
        'event_name' => 'PTC Fun Run',
        'category_name' => 'Sports Events',
        'venue_name' => 'School Grounds',
        'event_date' => '2027-04-02',
        'start_time' => '06:30:00',
        'end_time' => '09:00:00',
        'description' => 'Sample fun run event for wellness and school pride. This demo event can be modified or removed by the administrator as needed.',
        'image' => 'assets/images/sports-basketball.jpg',
        'maximum_participants' => 120,
    ],
    [
        'event_name' => 'Table Tennis Tournament',
        'category_name' => 'Sports Events',
        'venue_name' => 'School Gymnasium',
        'event_date' => '2027-05-16',
        'start_time' => '13:00:00',
        'end_time' => '17:00:00',
        'description' => 'Sample table tennis tournament designed to highlight student talent, sportsmanship, and healthy competition. Demo content only.',
        'image' => 'assets/images/sports-volleyball.jpg',
        'maximum_participants' => 32,
    ],
    [
        'event_name' => 'Programming Challenge 2027',
        'category_name' => 'Academic Events',
        'venue_name' => 'Computer Laboratory',
        'event_date' => '2027-01-27',
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'description' => 'Sample programming contest for students to showcase creativity, coding skills, and problem-solving. Demo event only.',
        'image' => 'assets/images/academic-programming.jpg',
        'maximum_participants' => 50,
    ],
    [
        'event_name' => 'IT and Technology Seminar',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Conference Room',
        'event_date' => '2027-02-19',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'description' => 'Sample seminar on current IT trends, tools, and digital innovation. This is a demo activity and may be adjusted by the administrator.',
        'image' => 'assets/images/academic-seminar.jpg',
        'maximum_participants' => 70,
    ],
    [
        'event_name' => 'Web Development Workshop',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Computer Laboratory',
        'event_date' => '2027-03-09',
        'start_time' => '13:00:00',
        'end_time' => '16:00:00',
        'description' => 'Sample workshop for front-end and back-end development skills. Demo event for presentation and editable by the administrator.',
        'image' => 'assets/images/academic-programming.jpg',
        'maximum_participants' => 45,
    ],
    [
        'event_name' => 'Quiz Bee Competition',
        'category_name' => 'Academic Events',
        'venue_name' => 'School Auditorium',
        'event_date' => '2027-04-15',
        'start_time' => '09:00:00',
        'end_time' => '11:30:00',
        'description' => 'Sample academic competition encouraging recall, teamwork, and general knowledge. This event is for demonstration and may be edited later.',
        'image' => 'assets/images/academic-seminar.jpg',
        'maximum_participants' => 55,
    ],
    [
        'event_name' => 'Research and Innovation Exhibition',
        'category_name' => 'Academic Events',
        'venue_name' => 'School Auditorium',
        'event_date' => '2027-05-24',
        'start_time' => '10:00:00',
        'end_time' => '15:00:00',
        'description' => 'Sample student research and innovation showcase. Demo content only, meant for event planning and presentation needs.',
        'image' => 'assets/images/academic-programming.jpg',
        'maximum_participants' => 100,
    ],
    [
        'event_name' => 'Cultural Dance Festival',
        'category_name' => 'Cultural Events',
        'venue_name' => 'School Auditorium',
        'event_date' => '2027-02-14',
        'start_time' => '18:00:00',
        'end_time' => '21:00:00',
        'description' => 'Sample cultural celebration featuring dance performances, artistry, and student pride. Demo event only.',
        'image' => 'assets/images/cultural-dance.jpg',
        'maximum_participants' => 200,
    ],
    [
        'event_name' => 'PTC Talent Showcase',
        'category_name' => 'Cultural Events',
        'venue_name' => 'School Auditorium',
        'event_date' => '2027-03-18',
        'start_time' => '18:30:00',
        'end_time' => '21:30:00',
        'description' => 'Sample talent showcase featuring music, dance, acting, and student creativity. Demo content only, editable by the administrator.',
        'image' => 'assets/images/cultural-music.jpg',
        'maximum_participants' => 150,
    ],
    [
        'event_name' => 'Music and Arts Festival',
        'category_name' => 'Cultural Events',
        'venue_name' => 'School Grounds',
        'event_date' => '2027-04-29',
        'start_time' => '15:00:00',
        'end_time' => '19:00:00',
        'description' => 'Sample festival featuring creative performances and student expression. This demo event can be updated anytime by the admin.',
        'image' => 'assets/images/cultural-music.jpg',
        'maximum_participants' => 180,
    ],
    [
        'event_name' => 'Student Organization Fair',
        'category_name' => 'Social Events',
        'venue_name' => 'School Grounds',
        'event_date' => '2027-05-07',
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'description' => 'Sample campus fair for student organizations, clubs, and community engagement. Demo event only and not an official commitment.',
        'image' => 'assets/images/social-workshop.jpg',
        'maximum_participants' => 220,
    ],
    [
        'event_name' => 'PTC Foundation Day Celebration',
        'category_name' => 'School Celebrations',
        'venue_name' => 'School Grounds',
        'event_date' => '2027-06-20',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'description' => 'Sample foundation day event for school pride, activities, and community celebration. Demo event content for presentation purposes.',
        'image' => 'assets/images/school-celebration.jpg',
        'maximum_participants' => 250,
    ],
    [
        'event_name' => 'Student Leadership Workshop',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Conference Room',
        'event_date' => '2027-01-30',
        'start_time' => '13:00:00',
        'end_time' => '15:30:00',
        'description' => 'Sample workshop intended to build confidence, communication skills, and leadership among student leaders. Demo event only.',
        'image' => 'assets/images/social-workshop.jpg',
        'maximum_participants' => 50,
    ],
    [
        'event_name' => 'Career Development Seminar',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Conference Room',
        'event_date' => '2027-02-27',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'description' => 'Sample seminar focused on career readiness, employability, and workplace preparation for students. Demo content only.',
        'image' => 'assets/images/academic-seminar.jpg',
        'maximum_participants' => 65,
    ],
    [
        'event_name' => 'Resume Writing Workshop',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Conference Room',
        'event_date' => '2027-03-25',
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'description' => 'Sample workshop to help students improve resumes, portfolios, and confidence in application preparation. Demo event content.',
        'image' => 'assets/images/social-workshop.jpg',
        'maximum_participants' => 40,
    ],
    [
        'event_name' => 'Entrepreneurship Seminar',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'Conference Room',
        'event_date' => '2027-04-12',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'description' => 'Sample seminar about entrepreneurial thinking, innovation, and student business ideas. Demo event only and editable by admin.',
        'image' => 'assets/images/academic-seminar.jpg',
        'maximum_participants' => 60,
    ],
    [
        'event_name' => 'Mental Wellness Awareness Seminar',
        'category_name' => 'Seminars and Workshops',
        'venue_name' => 'School Auditorium',
        'event_date' => '2027-06-08',
        'start_time' => '09:30:00',
        'end_time' => '11:30:00',
        'description' => 'Sample awareness seminar focused on mental wellness, resilience, and support resources for students. Demo content only.',
        'image' => 'assets/images/social-workshop.jpg',
        'maximum_participants' => 80,
    ],
];

foreach ($events as $event) {
    $categoryStmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ? LIMIT 1");
    $categoryStmt->execute([$event['category_name']]);
    $category = $categoryStmt->fetch();

    if (!$category) {
        continue;
    }

    $venueStmt = $pdo->prepare("SELECT id FROM venues WHERE venue_name = ? LIMIT 1");
    $venueStmt->execute([$event['venue_name']]);
    $venue = $venueStmt->fetch();

    if (!$venue) {
        continue;
    }

    $existsStmt = $pdo->prepare("SELECT id FROM events WHERE LOWER(event_name) = LOWER(?) LIMIT 1");
    $existsStmt->execute([$event['event_name']]);
    if ($existsStmt->fetch()) {
        continue;
    }

    $registrationDeadline = date('Y-m-d', strtotime($event['event_date'] . ' - 10 days'));

    $insertStmt = $pdo->prepare(
        "INSERT INTO events (
            category_id, venue_id, event_name, description, event_date, start_time, end_time,
            event_image, registration_deadline, maximum_participants, status, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', ?, NOW())"
    );

    $insertStmt->execute([
        (int) $category['id'],
        (int) $venue['id'],
        $event['event_name'],
        $event['description'],
        $event['event_date'],
        $event['start_time'],
        $event['end_time'],
        $event['image'],
        $registrationDeadline,
        (int) $event['maximum_participants'],
        $adminId,
    ]);
}

echo "Sample campus events inserted successfully.\n";
