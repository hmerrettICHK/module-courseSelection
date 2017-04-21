<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Modules\CourseSelection\Domain;

/**
 * Course Selection: courseSelectionChoice Table Gateway
 *
 * @version v14
 * @since   16th April 2017
 * @author  Sandra Kuipers
 */
class SelectionsGateway
{
    protected $pdo;

    public function __construct(\Gibbon\sqlConnection $pdo)
    {
        $this->pdo = $pdo;
    }

    // CHOICES

    public function selectCoursesByBlock($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);
        $sql = "SELECT courseSelectionBlockCourse.*, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort
                FROM courseSelectionBlockCourse
                JOIN gibbonCourse ON (courseSelectionBlockCourse.gibbonCourseID=gibbonCourse.gibbonCourseID)
                WHERE courseSelectionBlockID=:courseSelectionBlockID
                ORDER BY gibbonCourse.name";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectChoicesByBlockAndPerson($courseSelectionBlockID, $gibbonPersonIDStudent)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "SELECT courseSelectionChoice.gibbonCourseID, courseSelectionChoice.*
                FROM courseSelectionOfferingBlock
                JOIN courseSelectionBlockCourse ON (courseSelectionBlockCourse.courseSelectionBlockID=courseSelectionOfferingBlock.courseSelectionBlockID)
                JOIN courseSelectionChoice ON (courseSelectionBlockCourse.gibbonCourseID=courseSelectionChoice.gibbonCourseID)
                WHERE courseSelectionOfferingBlock.courseSelectionBlockID=:courseSelectionBlockID
                AND courseSelectionChoice.gibbonPersonIDStudent=:gibbonPersonIDStudent
                AND courseSelectionChoice.status <> 'Removed'
                GROUP BY courseSelectionChoice.gibbonCourseID
                ORDER BY courseSelectionChoice.status";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }

    public function selectChoiceByCourseAndPerson($gibbonCourseID, $gibbonPersonIDStudent)
    {
        $data = array('gibbonCourseID' => $gibbonCourseID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "SELECT * FROM courseSelectionChoice WHERE gibbonCourseID=:gibbonCourseID AND gibbonPersonIDStudent=:gibbonPersonIDStudent";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }

    public function insertChoice(array $data)
    {
        $sql = "INSERT INTO courseSelectionChoice SET gibbonSchoolYearID=:gibbonSchoolYearID, gibbonPersonIDStudent=:gibbonPersonIDStudent, gibbonCourseID=:gibbonCourseID, status=:status, gibbonPersonIDSelected=:gibbonPersonIDSelected, timestampSelected=:timestampSelected, notes=:notes";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function updateChoice(array $data)
    {
        $sql = "UPDATE courseSelectionChoice SET gibbonSchoolYearID=:gibbonSchoolYearID, status=:status, gibbonPersonIDSelected=:gibbonPersonIDSelected, timestampSelected=:timestampSelected, notes=:notes WHERE gibbonPersonIDStudent=:gibbonPersonIDStudent AND gibbonCourseID=:gibbonCourseID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function deleteChoice($courseSelectionChoiceID)
    {
        $data = array('courseSelectionChoiceID' => $courseSelectionChoiceID);
        $sql = "DELETE FROM courseSelectionChoice WHERE courseSelectionChoiceID=:courseSelectionChoiceID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function updateUnselectedChoicesBySchoolYearAndPerson($gibbonSchoolYearID, $gibbonPersonIDStudent, $courseIDList)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);

        if (!empty($courseIDList)) {
            $sql = "UPDATE courseSelectionChoice SET status='Removed'
                    WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                    AND gibbonPersonIDStudent=:gibbonPersonIDStudent
                    AND gibbonCourseID NOT IN ({$courseIDList})
                    AND (status='Requested' OR status='Approved')";
        } else {
            $sql = "UPDATE courseSelectionChoice SET status='Removed'
                    WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                    AND gibbonPersonIDStudent=:gibbonPersonIDStudent
                    AND (status='Requested' OR status='Approved')";
        }

        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    // LOG

    public function selectAllLogs($page = 1, $limit = 50)
    {
        $offset = ($page > 1)? ( ($page-1) * $limit) : 0;

        $sql = "SELECT courseSelectionLog.*, gibbonSchoolYear.name as schoolYearName, courseSelectionOffering.name as offeringName, gibbonPersonStudent.surname AS studentSurname, gibbonPersonStudent.preferredName AS studentPreferredName, gibbonPersonChanged.surname AS changedSurname, gibbonPersonChanged.preferredName AS changedPreferredName
                FROM courseSelectionLog
                JOIN courseSelectionOffering ON (courseSelectionOffering.courseSelectionOfferingID=courseSelectionLog.courseSelectionOfferingID)
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=courseSelectionLog.gibbonSchoolYearID)
                JOIN gibbonPerson AS gibbonPersonStudent ON (gibbonPersonStudent.gibbonPersonID=courseSelectionLog.gibbonPersonIDStudent)
                JOIN gibbonPerson AS gibbonPersonChanged ON (gibbonPersonChanged.gibbonPersonID=courseSelectionLog.gibbonPersonIDChanged)
                GROUP BY courseSelectionLog.courseSelectionLogID
                ORDER BY courseSelectionLog.timestampChanged DESC
                LIMIT {$limit} OFFSET {$offset}";
        $result = $this->pdo->executeQuery(array(), $sql);

        return $result;
    }

    public function insertLog(array $data)
    {
        $sql = "INSERT INTO courseSelectionLog SET gibbonSchoolYearID=:gibbonSchoolYearID, courseSelectionOfferingID=:courseSelectionOfferingID, gibbonPersonIDStudent=:gibbonPersonIDStudent, gibbonPersonIDChanged=:gibbonPersonIDChanged, timestampChanged=:timestampChanged, action=:action";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    // OFFERINGS

    public function selectChoiceOffering($gibbonSchoolYearID, $gibbonPersonIDStudent)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "SELECT courseSelectionOfferingID, gibbonSchoolYearID, gibbonPersonIDStudent FROM courseSelectionChoiceOffering WHERE gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonPersonIDStudent=:gibbonPersonIDStudent";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }

    public function insertChoiceOffering(array $data)
    {
        $sql = "INSERT INTO courseSelectionChoiceOffering SET gibbonSchoolYearID=:gibbonSchoolYearID, gibbonPersonIDStudent=:gibbonPersonIDStudent, courseSelectionOfferingID=:courseSelectionOfferingID ON DUPLICATE KEY UPDATE courseSelectionOfferingID=:courseSelectionOfferingID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function deleteChoiceOffering($gibbonSchoolYearID, $gibbonPersonIDStudent)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "DELETE FROM courseSelectionChoiceOffering WHERE gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonPersonIDStudent=:gibbonPersonIDStudent";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    // GRADES

    public function selectStudentReportGradesByDepartments($gibbonDepartmentIDList, $gibbonPersonIDStudent)
    {
        $data = array('gibbonDepartmentIDList' => $gibbonDepartmentIDList, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "(SELECT gradeID as grade, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                LEFT JOIN arrCriteria ON (gibbonCourse.gibbonCourseID=arrCriteria.subjectID AND (arrCriteria.criteriaType=2 || arrCriteria.criteriaType=4) )
                LEFT JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID AND arrReportGrade.studentID = gibbonCourseClassPerson.gibbonPersonID )
                LEFT JOIN arrReport ON (arrReport.reportID=arrReportGrade.reportID AND arrReport.schoolYearID=gibbonCourse.gibbonSchoolYearID )
                WHERE FIND_IN_SET(gibbonCourse.gibbonDepartmentID, :gibbonDepartmentIDList)
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonIDStudent
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND gibbonCourse.nameShort NOT LIKE '%HOMEROOM%'
                AND gibbonCourse.nameShort NOT LIKE '%Advisor%'
                AND arrReportGrade.reportGradeID IS NOT NULL
                GROUP BY gibbonCourse.gibbonCourseID
                ORDER BY arrCriteria.criteriaType DESC
            ) UNION ALL (
            SELECT DISTINCT grade, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM arrLegacyGrade
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=arrLegacyGrade.gibbonCourseID)
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                WHERE arrLegacyGrade.gibbonPersonID=:gibbonPersonIDStudent
                AND FIND_IN_SET(gibbonCourse.gibbonDepartmentID, :gibbonDepartmentIDList)
                AND arrLegacyGrade.reportTerm='Final'
                ) ORDER BY schoolYearName, courseOrder, courseNameShort";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }
}
