<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStoredProcedures extends Migration
{
    public function up()
    {
        $procedures = [
            'sp_CreateGenericProfile',
            'sp_CreateUpdate',
            'sp_GetStudentBoard',
            'sp_GetStudentDashboard',
            'sp_GetStudentFeed',
            'sp_GetTeacherDashboard',
            'sp_GetTeacherFeed',
            'sp_GetTeacherPendingRequests',
            'sp_GetTeacherStudentDirectory',
            'sp_ManageStudentEnrollment',
            'sp_SyncAccount',
        ];

        foreach ($procedures as $proc) {
            $this->db->query("DROP PROCEDURE IF EXISTS $proc");
        }

        // ==============================
        // 1. sp_CreateGenericProfile
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_CreateGenericProfile(
           IN p_account_id INT,
           IN p_school_id INT,
           IN p_profile_type_id INT,
           IN p_role_id INT,
           IN p_status_id INT,
           IN p_display_name VARCHAR(100),
           IN p_gender_id INT,
           IN p_designation_id INT,
           IN p_section_id INT,
           IN p_roll_number VARCHAR(20)
        )
        BEGIN
            DECLARE v_new_profile_id INT;

            INSERT INTO profiles (account_id, profile_type_id, display_name, designation_id, gender_id)
            VALUES (p_account_id, p_profile_type_id, p_display_name, p_designation_id, p_gender_id);

            SET v_new_profile_id = LAST_INSERT_ID();

            INSERT INTO school_memberships (profile_id, school_id, role_id, status_id)
            VALUES (v_new_profile_id, p_school_id, p_role_id, p_status_id);

            IF p_role_id = 3 AND p_section_id IS NOT NULL THEN
                INSERT INTO class_memberships (profile_id, section_id, roll_number, status_id)
                VALUES (v_new_profile_id, p_section_id, COALESCE(p_roll_number,'Pending'), p_status_id);
            END IF;

            SELECT v_new_profile_id AS newProfileId, 'Profile created successfully.' AS message;
        END
        ");

        // ==============================
        // 2. sp_CreateUpdate
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_CreateUpdate(
           IN p_school_id INT,
           IN p_source_profile_id INT,
           IN p_context_id INT,
           IN p_category_id INT,
           IN p_section_id INT,
           IN p_subject_id INT,
           IN p_text TEXT,
           IN p_visible_from DATETIME,
           IN p_expires_at DATETIME,
           IN p_is_locked INT
        )
        BEGIN
            DECLARE v_final_visible_from DATETIME;
            DECLARE v_final_expires_at DATETIME;

            IF p_visible_from IS NOT NULL THEN
                SET v_final_visible_from = p_visible_from;
            ELSE
                SET v_final_visible_from = TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '06:30:00');
            END IF;

            IF p_expires_at IS NOT NULL THEN
                SET v_final_expires_at = p_expires_at;
            ELSE
                SET v_final_expires_at = v_final_visible_from + INTERVAL 1 DAY;
            END IF;

            INSERT INTO updates (school_id, source_profile_id, context_id, category_id, section_id, subject_id, text, visible_from, expires_at, is_locked)
            VALUES (p_school_id, p_source_profile_id, p_context_id, p_category_id, p_section_id, p_subject_id, p_text, v_final_visible_from, v_final_expires_at, COALESCE(p_is_locked,0));

            SELECT LAST_INSERT_ID() AS newUpdateId;
        END
        ");

        // ==============================
        // 3. sp_GetStudentBoard
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetStudentBoard(IN p_student_profile_id INT)
        BEGIN
            DECLARE v_section_id INT;
            DECLARE v_total_updates INT DEFAULT 0;

            SELECT section_id INTO v_section_id
            FROM class_memberships
            WHERE profile_id = p_student_profile_id AND status_id = 2
            LIMIT 1;

            SELECT COUNT(id) INTO v_total_updates
            FROM updates
            WHERE section_id = v_section_id AND DATE(created_at) = CURDATE();

            SELECT v_total_updates AS boardTotalUpdates,
                   ta.is_class_teacher AS isClassTeacher,
                   ta.subject_id AS subjectId,
                   sub.name AS subjectName,
                   tp.id AS teacherProfileId,
                   tp.display_name AS teacherName,
                   md.name AS teacherDesignation,
                   mg.short_code AS teacherGenderCode,
                   ta.section_id AS sectionId,
                   (
                       SELECT COUNT(id)
                       FROM updates
                       WHERE section_id = v_section_id AND subject_id = ta.subject_id AND DATE(created_at) = CURDATE()
                   ) AS subjectTotalUpdates
            FROM teacher_allocations ta
            JOIN subjects sub ON ta.subject_id = sub.id
            JOIN profiles tp ON ta.teacher_profile_id = tp.id
            LEFT JOIN master_designations md ON tp.designation_id = md.id
            LEFT JOIN master_gender mg ON tp.gender_id = mg.id
            WHERE ta.section_id = v_section_id;
        END
        ");

        // ==============================
        // 4. sp_GetStudentDashboard
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetStudentDashboard(IN p_account_id INT)
        BEGIN
            SELECT s.id AS schoolId,
                   s.name AS schoolName,
                   s.address AS address,
                   mc.name AS city,
                   p.id AS profileId,
                   p.display_name AS profileName,
                   mstat.name AS statusName,
                   mas.name AS sessionName,
                   ms.name AS standardName,
                   sec.section_name AS sectionName,
                   sec.id AS sectionId,
                   cm.roll_number AS rollNumber,
                   (
                       SELECT COUNT(id) FROM updates WHERE section_id = cm.section_id AND DATE(created_at) = CURDATE()
                   ) AS todaysUpdates,
                   EXISTS(
                       SELECT 1 FROM updates u
                       JOIN master_update_categories muc ON u.category_id = muc.id
                       WHERE u.section_id = cm.section_id AND muc.severity_level = 2 AND DATE(u.created_at) = CURDATE()
                   ) AS hasUrgent,
                   s.is_active AS isActive
            FROM schools AS s
            LEFT JOIN master_cities AS mc ON s.city_id = mc.id
            LEFT JOIN school_memberships AS sm ON s.id = sm.school_id
            LEFT JOIN profiles AS p ON sm.profile_id = p.id
            LEFT JOIN academic_sessions AS acd ON acd.school_id = s.id AND acd.is_active = 1
            LEFT JOIN master_academic_sessions AS mas ON mas.id = acd.master_session_id
            LEFT JOIN class_memberships AS cm ON cm.profile_id = p.id
            LEFT JOIN master_statuses AS mstat ON mstat.id = cm.status_id
            LEFT JOIN sections AS sec ON sec.id = cm.section_id
            LEFT JOIN classes AS c ON c.id = sec.class_id
            LEFT JOIN master_standards AS ms ON ms.id = c.standard_id
            WHERE p.account_id = p_account_id AND sm.role_id = 3;
        END
        ");

        // ==============================
        // 5. sp_GetStudentFeed
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetStudentFeed(IN p_student_profile_id INT, IN p_subject_id INT)
        BEGIN
            DECLARE v_section_id INT;

            SELECT section_id INTO v_section_id
            FROM class_memberships
            WHERE profile_id = p_student_profile_id AND status_id = 2
            LIMIT 1;

            SELECT u.id AS updateId,
                   u.text AS text,
                   u.created_at AS createdAt,
                   u.expires_at AS expiresAt,
                   u.is_locked AS isLocked,
                   muc.id AS categoryId,
                   muc.name AS categoryName,
                   IF(muc.severity_level = 2,1,0) AS isUrgent,
                   sub.id AS subjectId,
                   sub.name AS subjectName,
                   tp.id AS teacherProfileId,
                   tp.display_name AS teacherName,
                   md.name AS teacherDesignation,
                   EXISTS(
                       SELECT 1 FROM acknowledgements ack
                       WHERE ack.update_id = u.id AND ack.profile_id = p_student_profile_id
                   ) AS isAcknowledgedByMe
            FROM updates u
            JOIN master_update_categories muc ON u.category_id = muc.id
            JOIN profiles tp ON u.source_profile_id = tp.id
            LEFT JOIN master_designations md ON tp.designation_id = md.id
            LEFT JOIN subjects sub ON u.subject_id = sub.id
            WHERE u.section_id = v_section_id
              AND (p_subject_id IS NULL OR p_subject_id = 0 OR u.subject_id = p_subject_id)
              AND (u.visible_from IS NULL OR u.visible_from <= NOW())
              AND (u.expires_at IS NULL OR u.expires_at >= NOW())
            ORDER BY u.created_at DESC;
        END
        ");

        // ==============================
        // 6. sp_GetTeacherDashboard
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetTeacherDashboard(IN p_account_id INT)
        BEGIN
            SELECT s.id AS schoolId,
                   s.name AS schoolName,
                   s.address AS address,
                   mc.name AS city,
                   p.id AS profileId,
                   p.display_name AS profileName,
                   md.name AS designationName,
                   mg.short_code AS genderCode,
                   COALESCE(
                       (
                           SELECT JSON_ARRAYAGG(
                               JSON_OBJECT(
                                   'allocationId', ta.id,
                                   'sectionId', ta.section_id,
                                   'className', CONCAT(ms.short_name,'-',sec.section_name),
                                   'subjectId', ta.subject_id,
                                   'subjectName', sub.name,
                                   'isClassTeacher', ta.is_class_teacher
                               )
                           )
                           FROM teacher_allocations ta
                           JOIN sections sec ON ta.section_id = sec.id
                           JOIN classes c ON sec.class_id = c.id
                           JOIN master_standards ms ON c.standard_id = ms.id
                           JOIN subjects sub ON ta.subject_id = sub.id
                           WHERE ta.teacher_profile_id = p.id
                       ), '[]'
                   ) AS allocationsJson
            FROM profiles p
            JOIN school_memberships sm ON p.id = sm.profile_id
            JOIN schools s ON sm.school_id = s.id
            LEFT JOIN master_cities mc ON s.city_id = mc.id
            LEFT JOIN master_designations md ON p.designation_id = md.id
            LEFT JOIN master_gender mg ON p.gender_id = mg.id
            WHERE p.account_id = p_account_id AND sm.role_id = 4;
        END
        ");

        // ==============================
        // 7. sp_GetTeacherFeed
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetTeacherFeed(IN p_teacher_profile_id INT, IN p_section_id INT, IN p_subject_id INT)
        BEGIN
            DECLARE v_total_students INT DEFAULT 0;

            SELECT COUNT(id) INTO v_total_students
            FROM class_memberships
            WHERE section_id = p_section_id AND status_id = 2;

            IF v_total_students = 0 THEN
                SET v_total_students = 1;
            END IF;

            SELECT sub.name AS headerTitle,
                   CONCAT(ms.name,'-',sec.section_name) AS headerSubtitle,
                   CONCAT(ms.short_name,'-',sec.section_name) AS classShortName,
                   u.id AS updateId,
                   u.text AS text,
                   u.created_at AS createdAt,
                   u.is_locked AS isLocked,
                   muc.id AS categoryId,
                   IF(muc.severity_level=2,1,0) AS isUrgent,
                   (SELECT COUNT(id) FROM acknowledgements WHERE update_id=u.id) AS ackCount,
                   v_total_students AS totalStudents,
                   IF(u.source_profile_id=p_teacher_profile_id AND u.is_locked=0,1,0) AS canEdit,
                   IF(u.source_profile_id=p_teacher_profile_id,1,0) AS canDelete
            FROM updates u
            JOIN master_update_categories muc ON u.category_id = muc.id
            JOIN sections sec ON u.section_id = sec.id
            JOIN classes c ON sec.class_id = c.id
            JOIN master_standards ms ON c.standard_id = ms.id
            JOIN subjects sub ON u.subject_id = sub.id
            WHERE u.section_id = p_section_id AND u.subject_id = p_subject_id
            ORDER BY u.created_at DESC;
        END
        ");

        // ==============================
        // 8. sp_GetTeacherPendingRequests
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetTeacherPendingRequests(IN p_teacher_profile_id INT, IN p_section_id INT)
        BEGIN
            SELECT cm.id AS membershipId,
                   p.id AS studentProfileId,
                   p.display_name AS studentName,
                   mg.short_code AS genderCode,
                   cm.roll_number AS requestedRollNumber,
                   sec.id AS sectionId,
                   CONCAT(ms.short_name,'-',sec.section_name) AS className,
                   COALESCE((
                       SELECT JSON_ARRAYAGG(JSON_OBJECT('name',sg.name,'relation',sg.relation,'phone',sg.phone,'whatsapp',sg.whatsapp_number,'isVerified',sg.is_verified))
                       FROM student_guardians sg
                       WHERE sg.student_profile_id = p.id
                   ), '[]') AS contactsJson
            FROM class_memberships cm
            JOIN profiles p ON cm.profile_id = p.id
            LEFT JOIN master_gender mg ON p.gender_id = mg.id
            JOIN sections sec ON cm.section_id = sec.id
            JOIN classes c ON sec.class_id = c.id
            JOIN master_standards ms ON c.standard_id = ms.id
            WHERE cm.status_id = 1
              AND cm.section_id IN (SELECT DISTINCT section_id FROM teacher_allocations WHERE teacher_profile_id = p_teacher_profile_id)
              AND (p_section_id IS NULL OR p_section_id = 0 OR cm.section_id = p_section_id)
            ORDER BY cm.id ASC;
        END
        ");

        // ==============================
        // 9. sp_GetTeacherStudentDirectory
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_GetTeacherStudentDirectory(IN p_teacher_profile_id INT, IN p_section_id INT)
        BEGIN
            SELECT p.id AS studentProfileId,
                   p.display_name AS studentName,
                   mg.short_code AS genderCode,
                   cm.roll_number AS rollNumber,
                   sec.id AS sectionId,
                   CONCAT(ms.name,'-',sec.section_name) AS className,
                   CONCAT(ms.short_name,'-',sec.section_name) AS shortClassName,
                   COALESCE((
                       SELECT JSON_ARRAYAGG(JSON_OBJECT('name',sg.name,'relation',sg.relation,'phone',sg.phone,'whatsappNumber',sg.whatsapp_number,'isVerified',sg.is_verified))
                       FROM student_guardians sg
                       WHERE sg.student_profile_id = p.id
                   ), '[]') AS guardiansJson
            FROM class_memberships cm
            JOIN profiles p ON cm.profile_id = p.id
            LEFT JOIN master_gender mg ON p.gender_id = mg.id
            JOIN sections sec ON cm.section_id = sec.id
            JOIN classes c ON sec.class_id = c.id
            JOIN master_standards ms ON c.standard_id = ms.id
            WHERE cm.status_id = 2
              AND cm.section_id IN (SELECT DISTINCT section_id FROM teacher_allocations WHERE teacher_profile_id = p_teacher_profile_id)
              AND (p_section_id IS NULL OR p_section_id = 0 OR cm.section_id = p_section_id)
            ORDER BY ms.order_index ASC, sec.section_name ASC, cm.roll_number ASC;
        END
        ");

        // ==============================
        // 10. sp_ManageStudentEnrollment
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_ManageStudentEnrollment(
           IN p_action INT,
           IN p_membership_id INT,
           IN p_target_section_id INT,
           IN p_roll_number VARCHAR(20),
           IN p_student_name VARCHAR(100),
           IN p_gender_id INT
        )
        BEGIN
            DECLARE v_profile_id INT;
            DECLARE v_message VARCHAR(255);

            SELECT profile_id INTO v_profile_id FROM class_memberships WHERE id = p_membership_id;

            IF p_action = 1 THEN
                UPDATE class_memberships SET status_id=2, roll_number=COALESCE(p_roll_number, roll_number) WHERE id=p_membership_id;
                SET v_message='Student request accepted successfully.';
            ELSEIF p_action = 2 THEN
                UPDATE class_memberships SET status_id=3 WHERE id=p_membership_id;
                SET v_message='Student request rejected.';
            ELSEIF p_action = 3 THEN
                UPDATE class_memberships SET section_id=p_target_section_id, status_id=2 WHERE id=p_membership_id;
                SET v_message='Student transferred successfully.';
            ELSEIF p_action = 4 THEN
                IF p_roll_number IS NOT NULL THEN
                    UPDATE class_memberships SET roll_number=p_roll_number WHERE id=p_membership_id;
                END IF;
                IF p_student_name IS NOT NULL OR p_gender_id IS NOT NULL THEN
                    UPDATE profiles SET display_name=COALESCE(p_student_name,display_name), gender_id=COALESCE(p_gender_id,gender_id)
                    WHERE id=v_profile_id;
                END IF;
                SET v_message='Student details updated successfully.';
            ELSE
                SET v_message='Invalid action requested.';
            END IF;

            SELECT 1 AS success, v_message AS confirmationMessage;
        END
        ");

        // ==============================
        // 11. sp_SyncAccount
        // ==============================
        $this->db->query("
        CREATE PROCEDURE sp_SyncAccount(
           IN p_uid VARCHAR(128),
           IN p_email VARCHAR(255),
           IN p_display_name VARCHAR(100),
           IN p_photo_url TEXT,
           IN p_is_email_verified INT
        )
        BEGIN
            INSERT INTO accounts (uid,email,display_name,photo_url,is_email_verified,last_login_at)
            VALUES (p_uid,p_email,p_display_name,p_photo_url,COALESCE(p_is_email_verified,0),CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                last_login_at = CURRENT_TIMESTAMP,
                email = VALUES(email),
                display_name = COALESCE(VALUES(display_name), display_name),
                photo_url = COALESCE(VALUES(photo_url), photo_url),
                is_email_verified = VALUES(is_email_verified);

            SELECT id AS accountId, is_active AS isActive, created_at AS createdAt
            FROM accounts
            WHERE uid = p_uid;
        END
        ");
    }

    public function down()
    {
        $procedures = [
            'sp_CreateGenericProfile',
            'sp_CreateUpdate',
            'sp_GetStudentBoard',
            'sp_GetStudentDashboard',
            'sp_GetStudentFeed',
            'sp_GetTeacherDashboard',
            'sp_GetTeacherFeed',
            'sp_GetTeacherPendingRequests',
            'sp_GetTeacherStudentDirectory',
            'sp_ManageStudentEnrollment',
            'sp_SyncAccount',
        ];

        foreach ($procedures as $proc) {
            $this->db->query("DROP PROCEDURE IF EXISTS $proc");
        }
    }
}
