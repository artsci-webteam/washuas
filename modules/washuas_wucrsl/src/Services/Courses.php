<?php

namespace Drupal\washuas_wucrsl\Services;

use Drupal\Core\Batch\BatchBuilder;

/**
 * Class Courses.
 *
 * @file providing helpful Course functions to use with the WUCrsl web service
 */

class Courses {
    /**
   * Loaded washuas_wucrsl settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;


  protected $units;
  protected $currentSemester;
  protected $mule;
  const SETTINGS = 'washuas_wucrsl.settings';
  const school = 'L';

  public function __construct() {
    $this->config = \Drupal::config(static::SETTINGS);
    $this->currentSemester = $this->getCurrentSemester();
    //$this->units = $this->getAcademicUnits();
  }

  public function getAcademicUnitOptions(){
    //initialize our return array
    $options = [];
    //get the soap /
    //@todo should we always call this ??
    $apiUnits = $this->executeMuleRequest('organization','academicunits',[],'organizations');
    //get the active configuration
    $configUnits = \Drupal::service('config.factory')->get(static::SETTINGS)->get('wucrsl_academic_units');
    foreach ( $apiUnits as $unit){
      if ( isset($unit['Hierarchy']['SuperiorOrganization_id']) && ( $unit['Hierarchy']['SuperiorOrganization_id'] == 'AU100069' ) ) {
        $unitName = $unit['OrganizationName'].'('.$unit['OrganizationCode'].')';
        $options[$unit['OrganizationCode']] = $unitName;
      }
    }

    //sort the options arrays
    if ( !empty($options) ) asort($options);

    return $options;
  }
  public function getCurrentSemester(){
    $now = new \DateTime('now');
    $month = $now->format('m');
    $day = $now->format('d');
    $year = $now->format('Y');

    //the semester year is the current year unless it's december and the 15th or after.
    $year = ( ( $month == 12 ) && ( $day >= 15 ) )? (intval($year) + 1) : $year;

    if ( ( $month < 5 ) || ( ( $month == 12 ) && ( $day >= 15 ) ) ){
      $semester='SPRING'; //if it's before may or it's dec and the 15th or after then we are in the spring semester, 02
    } else if ( $month < 8 ) { //semester = 03
      $semester='SUMMER'; //if it's between may and july then we are in the summer semester, 03
    } else {
      $semester='FALL'; //default, it's between aug and nov or in december before the 15th then we are in the fall semester, 05
    }
    return ["semester"=>$semester,"year"=>$year,"full"=>$semester.'_'.$year];
  }
  /**
   * Gets the semesters on for which we should import data
   *
   * @return array
   *  the semesters for which we'll run the import
   */
  function getSemestersToRun() {
    $now = new \DateTime('now');
    $month = $now->format('m');

    $semesters[] = $this->currentSemester["full"];
    switch($this->currentSemester["semester"]){
      case 'SPRING':
        $semesters[] = 'SUMMER_'.$this->currentSemester["year"];
        break;
      case 'SUMMER':
        $semesters[] = 'FALL_'.$this->currentSemester["year"];
        break;
      case 'FALL':
      default:
        $semesters[] = 'SPRING_'.(intval($this->currentSemester["year"]) + 1);
        break;
    }
    return $semesters;
  }

  function getSemesterTermsToDisplay() {

    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');

    //initialize the array we'll return;
    $semesters = [];

    //assign the current semester which is used in a couple of places
    $currentSemester = $this->currentSemester["semester"].$this->currentSemester["year"];

    // Given that I am a visitor, when I view the courses offered, then I will see courses
    // for the two prior semesters, the current semester and the next semester displayed on the website.
    switch($this->currentSemester["semester"]){
      case 'SPRING':
        $terms[] = "SUMMER_".$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = "FALL_".(intval($this->currentSemester["year"]) - 1); //one semester prior
        $terms[] = "SUMMER_".(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'SUMMER':
        $terms[] = "FALL_".$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = "SPRING_".$this->currentSemester["year"]; //one semester prior
        $terms[] = "FALL_".(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'FALL':
      default:
        $terms[] = "SPRING_".(intval($this->currentSemester["year"]) + 1); //next semester
        $terms[] = $currentSemester;
        $terms[] = "SUMMER_".$this->currentSemester["year"]; //one semester prior
        $terms[] = "SPRING_".$this->currentSemester["year"]; //two semesters prior
        break;
    }

    //all we need is the term id in this instance
    foreach ( $terms as $semester){
      $termID = $entityTools->getTermID("semester",$semester);
      if ( !empty($termID)){
        $semesters["options"][$termID] = "1";
        $defaultTerm = ( empty($defaultTerm) || ( $semester == $currentSemester ) )? $termID : $defaultTerm;
      }
    }
    $semesters["default"] = $defaultTerm ?? null;
    return $semesters;
  }
  /**
   * Gets the semesters for the current, previous, and next year
   *
   * @return array
   *  the semesters that we might run this for, used as options in the manual form
   */
  function getManualImportSemesters(){
    //initialize the array we'll return;
    $semesters = [];

    //assign the current semester which is used in a couple of places
    $currentSemester = $this->currentSemester["full"];

    // Given that I am a visitor, when I view the courses offered, then I will see courses
    // for the two prior semesters, the current semester and the next semester displayed on the website.
    switch($this->currentSemester["semester"]){
      case 'SPRING':
        $terms[] = 'SUMMER_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'FALL_'.(intval($this->currentSemester["year"]) - 1); //one semester prior
        $terms[] = 'SUMMER_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'SUMMER':
        $terms[] = 'FALL_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'SPRING_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'FALL_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'FALL':
      default:
        $terms[] = 'SPRING_'.(intval($this->currentSemester["year"]) + 1)."02"; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'SUMMER_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'SPRING_'.$this->currentSemester["year"]; //two semesters prior
        break;
    }

    //all we need is the term id in this instance
    foreach ( $terms as $semester){
      $semesters[$semester] = $semester;
    }
    return $semesters;
  }

 /**
   * This runs the import process through cron or from the manual form
   * @param string $semester
   *    the semester to run, if it's null we assign them
   *
   * @return array
   *
   * @throws
   */
  function getCoursesBatch($semester=null,$units=[],$cron=false):array {
    if ($cron){
      $title = 'Courses Import';
      $units = \Drupal::service('config.factory')->get(static::SETTINGS)->get('wucrsl_academic_units');
      $semesters = $this->getSemestersToRun();
    }else{ //add an informative title for manual imports
      $title = reset($units).' Courses Import for '.$semester;
      $semesters = [$semester];
    }

    //initialize courses batch
    $operations = [];
    $batch_builder = $this->initCoursesBatchBuilder($title);

    //if the academic units are empty then there isn't anything to do, set a message and return
    if (empty($units)){
      $this->addMessageAndLog('The departments must be selected in order to run the import. Please set the departments.');
      return $batch_builder->toArray();
    }

    foreach( $semesters as $semester ) {
     $entityTools = \Drupal::service('washuas_wucrsl.entitytools');
      $semesterTerm = $entityTools->createOrGetTerm('semester', $semester);
      $sections = $this->executeMuleRequest('academic','sections',['AcademicPeriod_id' => $semester],'sections');
      $sections = $this->createSections($sections,$semesterTerm);
      //next we'll loop through the courses and pull the associated data
      $courses = $this->getCourses($sections);
      /*//it's possible that we only have one course for the semester, this handles that
      $courses = (is_object($curriculum->Curriculum))? [$curriculum->Curriculum] : $curriculum->Curriculum;
      //if we're here then it's time to process the course data
      foreach( $courses as $course ) {
        $courseSections = $sections[trim($course->CourseNumber)] ?? [];
        if ( !empty($course->CourseTitle) && !empty($courseSections) ){
          $batch_builder->addOperation([$this, 'processCourse'], [$course,$courseSections,$semesterTerm]);
        }
      }
      */
    }

    //batch set and batch process calls go here
    return $batch_builder->toArray();
  }

  function getCourses($sections):array{
    //add call to pull the data from cache if it exists
    $data= [];
    //$fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]
    foreach ( $sections as $unit =>$semesters){
      foreach ( $semesters as $semester => $courses) {
        foreach ($courses as $courseID => $courseSections) {
          //if the unit is empty move to next iteration
          //get the curriculum
          $data = array_merge($data, $this->executeMuleRequest('academic', 'courses', ['AcademicUnit_id' => $unit,'StudentCourse_id' => $courseID], 'courses'));
        }
      }
    }
    return $data;
  }
  function processCourse($course,$courseSections,$semesterTerm, &$context){
    //If there is already a course then we will update
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');
    $unitName = trim($course->Departmentname);
    //add the fields that we will execute the query for in the entity tools service
    //these are stored as arrays under the operator we want to use
    $fields['='] = [
      'type'=>'courses',
      'field_course_id' => trim($course->CourseNumber),
      'field_course_dept_code' => trim($course->DeptCD),
      'field_course_semester' => $semesterTerm,
    ];
    //retrieve the existing nodes, but only if we have an existing taxonomy term as it's a filter
    $courseIDs = (empty($semesterTerm))? false : $entityTools->getNodeIDsByFields($fields);

    if (!isset($context['results'][$unitName][$course->DisplaySemester])){
      $context['results'][$unitName][$course->DisplaySemester] = ["updated"=>0,"added"=>0];
    }

    //create the paragraphs for the sections
    $courseSections = $this->createSectionParagraphs($courseSections);

    //if no nodes were returned then we'll create a new course
    if ( empty($courseIDs) ){
      //first attempt to get the term for the semester
      $new = $this->createCourse($course,$courseSections,$semesterTerm);
      $context['results'][$unitName][$course->DisplaySemester]['added']+= (empty($new))? 0:1;

    }else{
      $this->updateCourse($courseIDs,$course,$courseSections);
      $context['results'][$unitName][$course->DisplaySemester]['updated']+= 1;
    }
  }
    /**
     * Creates and saves the new course node
     *
     * @param object $course
     *  the soap course object
     *
     * @param array $sections
     *  the target ids of the section paragraphs
     *
     * @return object
     *
     * @throws
     */
    public function createCourse($course,$sections,$semester) {
        $courseID = trim($course->CourseNumber);
        $deptCD = trim($course->DeptCD);
        //if it's not set then create the term
        $entityTools = \Drupal::service('washuas_wucrsl.entitytools');
        //these are the fields that we'll send to entity tools for creation
        $fields = [
            'type' => 'courses',
            'title' => trim($course->CourseTitle),
            'field_course_id' => $courseID,
            'field_course_description' => $course->Description,
            'field_course_dept_code' => $deptCD,
            'field_course_department_name' => $course->Departmentname,
            'field_course_frequency' => $course->FrequencyOffered,
            'field_course_credits' => $course->Credits,
            'field_course_attributes' => $this->getCourseAttributes($course->CourseAttributes),
            'field_course_level' => $this->getCourseLevel($courseID),
            'field_course_sections'  => $sections,
            'field_course_semester' => $semester,
        ];
        //get the values that will carry over
        $fields += $this->getCurriculumSharedData($course);

        //create and save the course
        return $entityTools->createContent($fields);
    }

    /**
     * Updates an existing course with the fresh soap data
     *
     * @param string $courseID
     *  the node ID of the course we are updating
     *
     * @param object $course
     *  the soap course object we will be assigning data from
     *
     * @param array $sections
     *   the section paragraph ids we are assigning to the course
     *
     * @return void
     *
     * @throws
     */
    public function updateCourse($courseID,$course,$sections):void {
        //these are the fields that we'll send to entity tools for creation
        $fields = [
            'field_course_description' => $course->Description,
            'field_course_department_name' => $course->Departmentname,
            'field_course_frequency' => $course->FrequencyOffered,
            'field_course_credits' => $course->Credits,
            'field_course_attributes' => $this->getCourseAttributes($course->CourseAttributes),
            'field_course_sections'  => $sections,
        ];
      \Drupal::service('washuas_wucrsl.entitytools')->updateNodesByIDs($courseID,$fields);
    }

  public function coursesImportFinished($success, $results, $operations):void{
      foreach ($results as $department => $semesters) {
        foreach ($semesters as $semester => $courseLog) {
          $this->addMessageAndLog('The '. $department.' courses for '.$semester.' are imported. Added: ' . $courseLog["added"] . ', Updated: ' . $courseLog["updated"]);
        }
      }
  }

  function getCurriculumSharedFields(){
      return ["field_course_links",
              "field_search",
              "field_course_instructors",
              "field_course_requirements",
              "field_course_concentration"];
  }
    /**
   * Retrieves the fields from an existing course that are synced across course ids
   *
   * @param object $course
   *   the course object
   *
   *
   * @return array
   *  an array of values to be assigned to the new course
   */
  public function getCurriculumSharedData($course){
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');
    //first attempt to pull any courses with the id
    $fields['='] = [
      'type'=>'courses',
      'field_course_id' => trim($course->CourseNumber),
      'field_course_dept_code' => trim($course->DeptCD),
    ];

    //retrieve the existing nodes, but only if we have an existing taxonomy term as it's a filter
    $courseIDs = $entityTools->getNodeIDsByFields($fields);
    //get the course that we'll grab the data to carryover from
    $curriculumCourse = $this->getCurriculumCourse($courseIDs);

    //if we don't have a course then we done here
    if ( empty($curriculumCourse) ) {
      return[];
    }

    //these are the fields that carry over, we'll use this to loop through them
    $fields = $this->getCurriculumSharedFields();

    //initialize the array we'll return
    $carryOver = [];

    foreach ( $fields as $field ){
      //get the value from the course
      $value = $curriculumCourse->get($field)->getValue();
      //if we have a value it's at the below index, assign it
      switch($field){
        case 'field_course_concentration':
        case 'field_course_instructors':
        case 'field_course_requirements':
        case 'field_course_links':
          if (!empty($value)) $carryOver[$field] = $value;
          break;
        default:
          if (!empty($value)) $carryOver[$field] = $value[0]["value"];
          break;
      }
    }

    return $carryOver;
  }

  /**
   * Retrieves the course that we will use to pull data from when creating a new course
   *
   * @param array $courseIDs
   *   the course IDs that we'll pull nodes for
   *
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *  the course entity that we will pull the data from
   *
   * @throws
   */
  public function getCurriculumCourse($courseIDs){
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');

    //if there aren't any course ids then return null
    if (empty($courseIDs)){
      return null;
    }
    //initiate these to null
    $preserveSemester = 0;
    $preserveCourse = null;
    $courses = $entityTools->getNodesForIDs($courseIDs);
    foreach( $courses as $index => $course ){
      //get the course ID
      $courseID = $course->get("field_course_id")->getValue();
      //as soon as we find a courseID that isn't set to preserve we are done
      if ( $course->get("field_course_do_not_delete")->getValue()[0]["value"] === "0" ){
        return $course;
      }
      //first get the display semester value
      $semester =  $course->get("field_course_semester")->getValue();
      //get the semester/termName for the ID
      $semester = $entityTools->getTermNameById('semester', $semester[0]["target_id"] );
      //then reformat it to sort so we can compare
      $semester = $this->getSortSemester($semester);
      if ( $semester > $preserveSemester ){
          $preserveSemester = $semester;
        $preserveCourse = $course;
      }
    }

    return $preserveCourse;
  }

  /**
   * Makes the soap call given configuration and the function
   *
   * @param string $soapFunction
   *  the function that we'll call via soap
   *
   * @param integer $semester
   *  the semester for which we'll pull the data
   *
   * @param string $neededIndex
   *   an index to check cached data for, needs review
   *
   * @return array $data
   *  the associated soap data pulled from the request or cache
   *
   */
  function executeMuleRequest($api,$method,$query,$dataKey):array {
    $mule = \Drupal::service('washuas_wucrsl.mule');

    //we will use this to get the associated variables from the configuration, dev is the default
    $url = $this->config->get('wucrsl_request_url');
    //this retrieves the parameters for the api and the key for it's data in the array
    $parameters = $mule->getAPIParameters($api,$method,$query);
    //run the soap function to pull the data
    return $mule->executeFunction($url,$api,$method,$dataKey,$parameters);
  }

  /**
   * Creates the section paragraphs for the course
   *
   * @param array $sections
   *  the soap objects for the section course data
   *
   * @param string $displaySemester
   *  the formatted semester we will store in the section data
   *
   * @param string $department
   *   the department we will assign the section
   *
   * @return array
   *  an array of all the paragraph ids
   *
   * @throws
   */
  public function createSections(array $sections,$semester):array{
    $fields = [];
    //it's possible that we only have one section, this handles that
    foreach ($sections as $section){
      //pull the academic unit that we want this course for
      $unit = $this->getNeededAcademicUnit($section["SectionAcademicUnits"]);
      //if we don't have an associated unit we don't need this course, move on
      if ( empty($unit) ) continue;

      $fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]][] = [
        'type' => 'course_sections',
        'field_section_course_id' => [
          'value'  => trim($section["Course_id"]),
        ],
        'field_section_dept_code' => [
          'value'  => trim($unit),
        ],
        /*'field_room_schedules' => $this->getRoomSchedules($section->RoomSchedule),
        /*'field_section_instructors' => [
          'value'  => $this->getSectionInstructors($section->Instructors),
        ],*/
        'field_section_number' => [
          'value'  => $section["CourseSection_id"],
        ],
        'field_section_title' => [
          'value'  => $section["Title"],
        ],
        'field_course_semester' =>$section["AcademicPeriod_id"],
      ];
    }

    return $fields;
  }

  public function getNeededAcademicUnit(array $units):string{
    $activeUnits = \Drupal::service('config.factory')->get(static::SETTINGS)->get('wucrsl_academic_units');
    foreach($units as $unit){
      if (isset($activeUnits[$unit["AcademicUnit_id"]])){
        return $unit["AcademicUnit_id"];
      }
    }
    return '';
  }

  public function createSectionParagraphs($sections):array{
    $paragraphs = [];
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');

    foreach ($sections as $section){
      //get the semester if it exists, this returns false so we'll use that to determine
      //if we need to create and assign a new one or use this
      $paragraph = $entityTools->createContent($section,'paragraph');

      $paragraphs[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    return $paragraphs;
  }

  /**
   * Gets paragraphs for course attributes
   *
   * @param object $courseAttributes
   *  the soap objects for course attributes
   *
   * @return array
   *  an array of all the paragraph ids
   *
   * @throws
   */
  public function getCourseAttributes($courseAttributes): array {

    $paragraphs = [];

    if (isset($courseAttributes->CourseAttributes)  && is_array($courseAttributes->CourseAttributes)) {
      $paragraphs = $this->createCourseAttributes($courseAttributes->CourseAttributes);
    }

    return $paragraphs;
  }

  /**
   * Creates paragraphs for course attributes
   *
   * @param array $courseAttributes
   *  the soap objects for course attributes
   *
   * @return array
   *  an array of all the paragraph ids
   *
   * @throws
   */
  protected function createCourseAttributes(array $courseAttributes): array {

    $paragraphs = [];
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');

    foreach ( $courseAttributes as $index => $attributes ) {
      $fields = [
        'type' => 'course_attributes',
        'field_ca_full_name' => [
          'value'  => $attributes->FullName,
        ],
        'field_ca_group' => [
          'value'  => $attributes->ATTRGroup,
        ],
        'field_ca_owner_full_name' => [
          'value'  => $attributes->ATTROwnerFullName,
        ],
        'field_ca_owner_short_name' => [
          'value'  => $attributes->ATTROwnerShortName,
        ],
        'field_ca_short_name' => [
          'value'  => $attributes->Shortname,
        ],
      ];
      $paragraph = $entityTools->createContent($fields,'paragraph');

      $paragraphs[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    return $paragraphs;
  }

  /**
   * Gets the course level from the course id value
   *
   * @param string $courseID
   *  the soap objects for course attributes
   *
   * @return \Drupal\taxonomy\Entity\Term|FALSE
   *  the taxonomy term for course level
   *
   * @throws
   */
  public function getCourseLevel($courseID)
  {
    // Course Level is based on the first digit of the Course Code number
    switch(substr($courseID, 0, 1)){
      case '5':
        $courseLevel =  500;
        break;
      case '4':
        $courseLevel =  400;
        break;
      case '3':
        $courseLevel =  300;
        break;
      case '2':
        $courseLevel =  200;
        break;
      case '1':
        $courseLevel =  100;
        break;
      default:
        $courseLevel =  0;
        break;
    }
    //attempt to pull the taxonomy term
    $entityTools = \Drupal::service('washuas_wucrsl.entitytools');
    $term = $entityTools->getTerm('course_level', $courseLevel);
    //return the pulled taxonomy term or the newly created one
    return (!$term)? $entityTools->createTerm('course_level', $courseLevel) : $term;
  }

  /**
   * Gets the room schedule paragraph ids
   *
   * @param object $roomSchedule
   *  the room schedule object
   *
   * @return array
   *  an array of the room schedule paragraph ids
   *
   * @throws
   */
  public function getRoomSchedules($roomSchedule):array{

    $schedules = [];

    if (!isset($roomSchedule->RoomSchedule)){
      $missing = true;
    }else if (is_array($roomSchedule->RoomSchedule)){
      for ($i = 0; $i < count($roomSchedule->RoomSchedule); $i++) {
        $schedules[] = $this->createRoomSchedule($roomSchedule->RoomSchedule[$i]);
      }
    }else if (is_object($roomSchedule->RoomSchedule)){
      $schedules[] = $this->createRoomSchedule($roomSchedule->RoomSchedule);
    }

    return $schedules;
  }

  /**
   * Creates the room schedule paragraphs and returns an array of their ids
   *
   * @param object $roomSchedule
   *  the room schedule object
   *
   * @return array
   *  an array of the room schedule paragraph ids
   *
   * @throws
   */
  public function createRoomSchedule($roomSchedule)
  {

    $days = $this->getDays($roomSchedule->Day);

    //reformat the dateTime and clear out any values of 12:00AM
    $startTime = date('h:i A', strtotime($roomSchedule->StartTime));
    $startTime = ($startTime == "12:00 AM") ? "": $startTime;

    //reformat the dateTime and clear out any values of 12:00AM
    $endTime = date('h:i A', strtotime($roomSchedule->Endtime)); //Endtime - time needs to be lowercase >:|
    $endTime = ($startTime == "12:00 AM") ? "": $endTime;

    $building = ($roomSchedule->Bldg == 9999) ? "TBA" : $roomSchedule->Bldg;

    //build the room_schedule paragraph
    $fields = [
      'type' => 'room_schedule',
      'field_building' => [
        'value'  => $building,
      ],
      'field_day' => [
        'value'  => $days,
      ],
      'field_end_time' => [
        'value'  => $endTime,
      ],
      'field_room' => [
        'value'  => $roomSchedule->Room,
      ],
      'field_start_time' => [
        'value'  => $startTime,
      ],
    ];
    $paragraph = \Drupal::service('washuas_wucrsl.entitytools')->createContent($fields,'paragraph');

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

  /**
   * Reformat the days value for Room Schedule usage
   *
   * @param string $days
   *  the room schedule object
   *
   * @return string
   *  the reformatted days of the course section
   *
   * @throws
   */
  public function getDays(string $days):string
  {
    // The format that is being returned has - if that day isn't assigned
    // it has the first letter of the day if it does
    // M------ or -T----- or --W----
    if (trim($days) == 'TBD') {
      return 'TBD';
    }
    if (trim($days) == 'TBA') {
      return 'TBA';
    }

    // Check if this is a correctly formatted date list
    if ($days[0] !== '_' && $days[0] !== 'M') {
      $daysFormatted = array();
      for ($i = 0; $i < 7; $i++) {
        if ($days) {
          if ($days[$i] !== '-') {
            switch ($i) {
              case '0':
                $daysFormatted[] = 'M';
                break;
              case '1':
                $daysFormatted[] = 'T';
                break;
              case '2':
                $daysFormatted[] = 'W';
                break;
              case '3':
                $daysFormatted[] = 'R';
                break;
              case '4':
                $daysFormatted[] = 'F';
                break;
              case '5':
                $daysFormatted[] = 'Sat';
                break;
              case '6':
                $daysFormatted[] = 'Sun';
                break;
            }
          }
        }
      }
      return join("-", $daysFormatted);
    } else {
      return trim($days);
    }
  }

  /**
   * Get the instructor names from the object
   *
   * @param object $instructors
   *  the instructors object
   *
   * @return string
   *  the list of instructor names
   *
   * @throws
   */
  public function getSectionInstructors($instructors):string {
    if (empty((array) $instructors)) {
      return '';
    }

    if (is_array($instructors->Instructors)) {
      $names = [];
      foreach ($instructors->Instructors as $id => $i) {
        $names[] = $i->teacherName;
      }
      return substr(implode(', ', $names), 0, 254);
    }
    else {
      return $instructors->Instructors->teacherName;
    }
  }

  /**
   * Reformat the display semester to sort semester
   *
   * @param string $semester
   *  the display semester we'll reformat
   *
   * @return string|FALSE
   *  the sort Semester
   *
   * @throws
   */
  function getSortSemester(string $semester):string {
    $semester = trim( (string) $semester);
    if(strlen($semester) != 6)
      return FALSE;

    $seasons = array(
      'SPRING' => '02',
      'SUMMER' => '03',
      'FALL' => '05',
    );

    $season = substr($semester, 0, 2);
    $year = substr($semester, 2);

    return isset($seasons[$season]) ? $year . $seasons[$season] : FALSE;
  }
  function sortSemesters($a, $b) {
    $sortA = $this->getSortSemester($a);
    $sortB = $this->getSortSemester($b);

    if ($sortA == $sortB) {
      return 0;
    }
    return ($sortA > $sortB) ? -1 : 1;
  }
  public function initCoursesBatchBuilder($title):BatchBuilder{
    return (new BatchBuilder())
                ->setFile(\Drupal::service('module_handler')->getModule('washuas_wucrsl')->getPath(). '/src/Services/Courses.php')
                ->setTitle(t($title))
                ->setFinishCallback([$this, 'coursesImportFinished'])
                ->setInitMessage(t('The courses import is starting'))
                ->setProgressMessage(t('Processed @current out of @total.'))
                ->setErrorMessage(t('The SOAP batch import has encountered an error'));
  }
  public function addMessageAndLog($message):void{
    $messenger = \Drupal::service('messenger')->addMessage($message);
    \Drupal::service('logger.factory')->get('washuas_wucrsl')->notice($message);
  }
}
