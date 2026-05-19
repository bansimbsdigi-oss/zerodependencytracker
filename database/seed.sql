-- Insert test problem areas
INSERT IGNORE INTO problem_areas (id, area_name, is_active, display_order) VALUES
(1, 'Upper Body', 1, 1),
(2, 'Lower Body', 1, 2),
(3, 'Core / Back', 1, 3);

-- Insert sections for Upper Body
INSERT IGNORE INTO question_sections (id, area_id, section_name, display_order) VALUES
(1, 1, 'MOBILITY',                 0),
(2, 1, 'STRENGTH',                 1),
(3, 1, 'ENDURANCE',                2),
(4, 1, 'CONTROL & BALANCE',        3),
(5, 1, 'FUNCTIONAL INDEPENDENCE',  4),
(6, 1, 'CONFIDENCE & BODY TRUST',  5);

-- C2: No default admin credentials. Use the CLI setup script to create the first admin:
--   php artisan admin:create  (or run the equivalent setup script)
-- Never commit real admin credentials to source control.

INSERT IGNORE INTO questions (sno, question_text, question_type, rating_min, rating_max, flag) VALUES
(1, 'How comfortable is the affected area during daily activity today?', 'rating', 1, 10, 1),
(2, 'How has your movement range changed since the previous audit?', 'mcq', 1, 5, 1),
(3, 'How much pain or stiffness do you feel after completing your exercises?', 'mcq', 1, 5, 1),
(4, 'Which activities can you do confidently now?', 'multi_select', 1, 5, 1),
(5, 'How consistently did you complete the prescribed exercises this cycle?', 'mcq', 1, 5, 1),
(6, 'Share any pain, stiffness, or concern your coach should know.', 'text', 1, 5, 1),
(7, 'How confident are you about continuing your recovery plan?', 'rating', 1, 5, 1);

INSERT IGNORE INTO options (question_id, option_text, points, display_order) VALUES
(2, 'Movement feels worse than before', 0, 1),
(2, 'No major change yet', 2, 2),
(2, 'Slightly better movement', 4, 3),
(2, 'Significantly better movement', 6, 4),
(3, 'Severe pain or stiffness', 0, 1),
(3, 'Moderate pain or stiffness', 2, 2),
(3, 'Mild pain or stiffness', 4, 3),
(3, 'No pain or stiffness', 6, 4),
(4, 'Basic daily movement', 1, 1),
(4, 'Stairs, squats, or overhead reach', 1, 2),
(4, 'Lifting light objects safely', 1, 3),
(4, 'Sleeping comfortably', 1, 4),
(4, 'Work or home routine without help', 1, 5),
(5, 'Rarely completed them', 0, 1),
(5, 'Completed them 2-3 days per week', 2, 2),
(5, 'Completed them 4-5 days per week', 4, 3),
(5, 'Completed them daily', 6, 4);

INSERT IGNORE INTO question_area_map (question_id, area_id)
SELECT q.sno, pa.id
FROM questions q
CROSS JOIN problem_areas pa
WHERE q.sno BETWEEN 1 AND 7;
