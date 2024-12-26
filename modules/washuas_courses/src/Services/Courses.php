<?php

namespace Drupal\washuas_courses\Services;

use Drupal\Core\Batch\BatchBuilder;

/**
 * Class Courses.
 *
 * @file providing helpful Course functions to use with the Courses web service
 */

class Courses {
    /**
   * Loaded washuas_courses settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;


  protected $units;
  protected $currentSemester;
  protected $mule;
  const SETTINGS = 'washuas_courses.settings';
  const school = 'L';

  public function __construct() {
    $this->config = \Drupal::config(static::SETTINGS);
    $this->currentSemester = $this->getCurrentSemester();
    //$this->units = $this->getAcademicUnits();
  }

  /**
   * Returns a list of academic unit options for the academic unit form of options pulled from the mulesoft api
   *
   * @return array
   *  the semesters for which we'll run the import
   */
  public function getAcademicUnitOptions(){
    //initialize our return array
    $options = [];
    //get the soap /
    $apiUnits = $this->executeMuleRequest(null,'organization','academicunits',[],'organizations',true);
    //get the active configuration
    $configUnits = \Drupal::service('config.factory')->get(static::SETTINGS)->get('courses_academic_units');
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

  /**
   * Returns an array of data regarding the current semester
   *
   * @return array
   *  the semesters for which we'll run the import
   */
  public function getCurrentSemester(){
    $now = new \DateTime('now');
    $month = $now->format('m');
    $day = $now->format('d');
    $year = $now->format('Y');

    //the semester year is the current year unless it's december and the 15th or after.
    $year = ( ( $month == 12 ) && ( $day >= 15 ) )? (intval($year) + 1) : $year;

    if ( ( $month < 5 ) || ( ( $month == 12 ) && ( $day >= 15 ) ) ){
      $semester='Spring'; //if it's before may or it's dec and the 15th or after then we are in the spring semester, 02
    } else if ( $month < 8 ) { //semester = 03
      $semester='Summer'; //if it's between may and july then we are in the summer semester, 03
    } else {
      $semester='Fall'; //default, it's between aug and nov or in december before the 15th then we are in the fall semester, 05
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
      case 'Spring':
        $semesters[] = 'Summer_'.$this->currentSemester["year"];
        break;
      case 'Summer':
        $semesters[] = 'Fall_'.$this->currentSemester["year"];
        break;
      case 'Fall':
      default:
        $semesters[] = 'Spring_'.(intval($this->currentSemester["year"]) + 1);
        break;
    }
    return $semesters;
  }

  /**
   * This gets the active semesters we want to display on the site, it's used for the landing page
   *
   * @return array
   * a comma seperated list of periods this is offered in
   *
   * @throws
   */
  function getSemesterTermsToDisplay():array {

    $entityTools = \Drupal::service('washuas_courses.entitytools');

    //initialize the array we'll return;
    $semesters = [];

    //assign the current semester which is used in a couple of places
    $currentSemester = $this->currentSemester["semester"].$this->currentSemester["year"];

    // Given that I am a visitor, when I view the courses offered, then I will see courses
    // for the two prior semesters, the current semester and the next semester displayed on the website.
    switch($this->currentSemester["semester"]){
      case 'Spring':
        $terms[] = 'Summer_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Fall_'.(intval($this->currentSemester["year"]) - 1); //one semester prior
        $terms[] = 'Summer_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'Summer':
        $terms[] = 'Fall_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Spring_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'Fall_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'Fall':
      default:
        $terms[] = 'Spring_'.(intval($this->currentSemester["year"]) + 1); //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Summer_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'Spring_'.$this->currentSemester["year"]; //two semesters prior
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
      case 'Spring':
        $terms[] = 'Summer_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Fall_'.(intval($this->currentSemester["year"]) - 1); //one semester prior
        $terms[] = 'Summer_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'Summer':
        $terms[] = 'Fall_'.$this->currentSemester["year"]; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Spring_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'Fall_'.(intval($this->currentSemester["year"]) - 1); //two semesters prior
        break;
      case 'Fall':
      default:
        $terms[] = 'Spring_'.(intval($this->currentSemester["year"]) + 1)."02"; //next semester
        $terms[] = $currentSemester;
        $terms[] = 'Summer_'.$this->currentSemester["year"]; //one semester prior
        $terms[] = 'Spring_'.$this->currentSemester["year"]; //two semesters prior
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
   * @param array $units
   * the academic units we will process
   *
   * @param bool $cron
   * this implies this is being ran from cron and makes assumptions accordingly
   * @return array
   *
   * @throws
   */
  function getSectionPullBatches(string $semester='',$units=[],$cron=false):array {
    if ($cron){
      $title = 'Courses Import';
      $units = \Drupal::service('config.factory')->get(static::SETTINGS)->get('courses_academic_units');
      $semesters = $this->getSemestersToRun();
    }else{ //add an informative title for manual imports
      $title = reset($units).' Courses Import for '.$semester;
      $semesters = [$semester];
    }

    //if the academic units are empty then there isn't anything to do, set a message and return
    if (empty($units)){
      $this->addMessageAndLog('The departments must be selected in order to run the import. Please set the departments.');
      return [];
    }

    //initialize the batches we will return
    $batch_builder = $this->initSectionsBatchBuilder();
    //create the section api batches for each semester
    foreach( $semesters as $semester ) {
      $entityTools = \Drupal::service('washuas_courses.entitytools');
      $sectionBatch = $this->executeMuleRequest($batch_builder,'academic','sections',['AcademicPeriod_id' => $semester],'sections',true,$semester);
    }

    return $batch_builder->toArray();
  }

  /**
   * Creates and saves the new course node
   *
   * @param array $periodsOffered
   *  a list of all the periods in which the course is offered
   *
   * @return string
   * a comma seperated list of periods this is offered in
   *
   * @throws
   */
  function getPeriodsOffered(array $periodsOffered):string{
    $frequency = '';
    foreach($periodsOffered as $period){
      $frequency = (empty($frequency))?$period['AcademicPeriodsOfferedType_id']:','.$period['AcademicPeriodsOfferedType_id'];
    }
    return $frequency;
  }

  /**
   * Creates and saves the new course node
   *
   * @param array $course
   *  the course array to process
   *
   * @param string $courseID
   *   the courseID for the course
   *
   * @param string $semester
   *  the semester id for this course ex.Fall_2024
   *
   * @param string $unit
   *   the academic unit that applies to this course
   *
   * @param array $context
   *    the context array passed to the function by batch processing
   *
   * @return void
   *
   * @throws
   */
  function processCourse(array $curriculum,string $courseID,string $semester,string $unit,&$context):void{
    //pull the associate course data from mulesoft as we only have sections at present
    $course = $this->executeMuleRequest(null,'academic', 'courses', ['AcademicUnit_id' => $unit,'StudentCourse_id' => $courseID],'courses');

    //If there is already a course then we will update
    $entityTools = \Drupal::service('washuas_courses.entitytools');
    $unitName = $course['course']['AcademicUnits'][0]['AcademicUnit'];
    //add the fields that we will execute the query for in the entity tools service
    //these are stored as arrays under the operator we want to use
    $fields['='] = [
      'type'=>'courses',
      'field_course_id' => $course['course']['Course_id'],
      'field_course_dept_code' => $course['course']['AcademicUnits'][0]['AcademicUnit_id'],
      'field_course_semester' => $curriculum['semester'],
    ];

    //retrieve the existing nodes, but only if we have an existing taxonomy term as it's a filter
    $courseIDs = (empty($semesterTerm))? false : $entityTools->getNodeIDsByFields($fields);

    if (!isset($context['results'][$unitName][$semester])){
      $context['results'][$unitName][$semester] = ["updated"=>0,"added"=>0];
    }

    //create the paragraphs for the sections
    $courseSections = $this->createSectionParagraphs($course['sections']);

    //if no nodes were returned then we'll create a new course
    if ( empty($courseIDs) ){
      //first attempt to get the term for the semester
      $new = $this->createCourse($course['course'],$courseSections,$semesterTerm);
      $context['results'][$unitName][$semester]['added']+= (empty($new))? 0:1;
    }else{
      $this->updateCourse($courseIDs,$course['course'],$courseSections);
      $context['results'][$unitName][$semester]['updated']+= 1;
    }
  }

  /**
     * Creates and saves the new course node
     *
     * @param array $course
     *  the soap course object
     *
     * @param array $sections
     *  the target ids of the section paragraphs
     *
     * @param $semester
     *   the taxonomy term for the semester
     *
     * @return object
     *
     * @throws
     */
    public function createCourse(array $course,array $sections,$semester) {
        //if it's not set then create the term
        $entityTools = \Drupal::service('washuas_courses.entitytools');
        //these are the fields that we'll send to entity tools for creation
        $fields = [
          'type' => 'courses',
          'title' => $course['CourseTitle'],
          'field_course_id' => $course['Course_id'],
          'field_course_description' => $course['Description'],
          'field_course_dept_code' => $course['AcademicUnits'][0]['AcademicUnit_id'],
          'field_course_department_name' => $course['AcademicUnits'][0]['AcademicUnit'],
          'field_course_credits' => $course['MaximumUnits'],
          'field_course_level' => $this->getCourseLevel($course['Listings'][0]['CourseNumber']),
          'field_course_sections'  => $sections,
          'field_course_semester' => $semester,
        ];
        if (array_key_exists('CourseTags',$course)){
          $fields['field_course_attributes'] = $this->getCourseAttributes($course['CourseTags']);
        }
        if (array_key_exists('PeriodsOffered',$course)) {
          $fields['field_course_frequency'] = $this->getPeriodsOffered($course['PeriodsOffered']);
        }

      //get the values that will carry over
        $fields += $this->getCurriculumSharedData($course);

        //create and save the course
        return $entityTools->createContent($fields);
    }

    /**
     * Updates an existing course with the fresh soap data
     *
     * @param array $courseID
     *  the node ID of the course we are updating
     *
     * @param array $course
     *  the soap course object we will be assigning data from
     *
     * @param array $sections
     *   the section paragraph ids we are assigning to the course
     *
     * @return void
     *
     * @throws
     */
    public function updateCourse(array $courseID,array $course,array $sections):void {
      //these are the fields that we'll send to entity tools for creation
      $fields = [
        'field_course_description' => $course['Description'],
        'field_course_department_name' => $course['AcademicUnits'][0]['AcademicUnit'],
        'field_course_credits' => $course['MaximumUnits'],
        'field_course_sections'  => $sections,
      ];
      if (array_key_exists('CourseTags',$course)){
        $fields['field_course_attributes'] =  $this->getCourseAttributes($course['CourseTags']);
      }
      if (array_key_exists('PeriodsOffered',$course)) {
        $fields['field_course_frequency'] = $this->getPeriodsOffered($course['PeriodsOffered']);
      }

      \Drupal::service('washuas_courses.entitytools')->updateNodesByIDs($courseID,$fields);
    }

  /**
   * Retrieves the fields from an existing course that are synced across course ids
   *
   * @param $success
   *  this is sent to the function via batch builder finish callback
   *
   * @param $results
   * this is sent to the function via batch builder finish callback
   *
   * @param $operations
   *  this is sent to the function via batch builder finish callback
   *
   */
  public function coursesImportFinished($success, $results, $operations):void{
      foreach ($results as $department => $semesters) {
        foreach ($semesters as $semester => $courseLog) {
          $this->addMessageAndLog('The '. $department.' courses for '.$semester.' are imported. Added: ' . $courseLog["added"] . ', Updated: ' . $courseLog["updated"]);
        }
      }
  }

  /**
   * sends the list of fields that carry over across semesters
   *
   * @return array
   *  an array of the shared values to be assigned to the new course
   */
  function getCurriculumSharedFields():array{
      return ["field_course_links",
              "field_search",
              "field_course_instructors",
              "field_course_requirements",
              "field_course_concentration"];
  }
    /**
   * Retrieves the fields from an existing course that are synced across course ids
   *
   * @param array $course
   *   the course object
   *
   *
   * @return array
   *  an array of values to be assigned to the new course
   */
  public function getCurriculumSharedData(array $course):array{
    $entityTools = \Drupal::service('washuas_courses.entitytools');
    //first attempt to pull any courses with the id
    $fields['='] = [
      'type'=>'courses',
      'field_course_id' => $course['Course_id'],
      'field_course_dept_code' => $course['AcademicUnits'][0]['AcademicUnit_id'],
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
   * @return \Drupal\Core\Entity\EntityInterface|array
   *
   *  the course entity that we will pull the data from
   *
   * @throws
   */
  public function getCurriculumCourse(array $courseIDs){
    $entityTools = \Drupal::service('washuas_courses.entitytools');

    //if there aren't any course ids then return null
    if (empty($courseIDs)){
      return [];
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
   * @param string $api
   *  the name of the api we will call
   *
   * @param string $method
   *   the method of the api we will call
   *
   * @param array $query
   *    the query to send to the api call
   *
   * @param string $dataKey
   * the index in which data will be available after the api call
   *
   * @param boolean $useCache
   *  should the returned api data be saved to and pulled from cache
   *
   * @param string $cacheAppend
   *   an additional string to add to where to save/pull your cache data from
   *
   * @return array $data
   *  the associated soap data pulled from the request or cache
   *
   */
  function executeMuleRequest($builder, string $api,string $method,array $query,string $dataKey,bool $useCache=false,string $cacheAppend=''):array {
    $mule = \Drupal::service('washuas_courses.mule');
    //if for cache purposes we have something to append to the soap function we do it here
    $cache = \Drupal::service('washuas_courses.cache');
    $cacheName = (empty($cacheAppend)) ? 'washuas_courses_'.$api.'_'.$method : 'washuas_courses_'.$api.'_'.$method.'_'.$cacheAppend ;
    //pull the associated data from cache
    $data = ($useCache)? $cache->getDataFromCache($this->config->get('courses_cache_api'),$cacheName):[];
    //if we were not able to get the needed data from cache then we will attempt to pull it from soap
    if (empty($data)) {

      //we will use this to get the associated variables from the configuration, dev is the default
      $url = $this->config->get('courses_request_url');
      //this retrieves the parameters for the api and the key for it's data in the array
      $parameters = $mule->getAPIParameters($api, $method, $query);
      //if we are retrieving academic units then we will not use batch processing at this time
      if ( empty($builder) ){
        return $mule->executeFunction($url, $api, $method, $dataKey, $parameters);
      }else{
        $mule->setBatchRequests($builder,$url,$api,$method,$dataKey,$parameters);
        return [];
      }
    }
    //save the data to cache
    if ($useCache){
      $cache->saveDataToCache($cacheName,$data);
    }

    return $data;
  }

  /**
   * Creates the section paragraphs for the course
   *
   * @param array $data
   *  the array for the section course data
   *
   * @return void
   *
   * @throws
   */
  public function processSections(array $data):void{
    $sections = $this->createSections($data);
    $batch_builder = $this->initCoursesBatchBuilder('Mulesoft Courses Import');

    //$fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]['sections'][$section["CourseSection"]]
    foreach($sections as $unit=>$semesters){
      foreach($semesters as $semester => $courses){
        foreach($courses as $courseID =>$course){
          $batch_builder->addOperation([$this, 'processCourse'], [$course,$courseID,$semester,$unit]);
        }
      }
    }
    $queue = \Drupal::service('queue')->get('courses_cron_import');
    //add the batch to the queue where it'll be processed
    $queue->createItem($batch_builder->toArray());
  }

  /**
   * Creates the section paragraphs for the course
   *
   * @param array $sections
   *  the array for the section course data
   *
   * @return array
   *  an array of all the sections that are almost ready to create paragraphs for
   *
   * @throws
   */
  public function createSections(array $data):array{
    $fields = [];
    $entityTools = \Drupal::service('washuas_courses.entitytools');

    //the data basically comes over in pages with each page containing multiple sections
    foreach ($data as $sections) {
      foreach($sections as $section){
        //pull the academic unit that we want this course for
        $unit = (array_key_exists('SectionAcademicUnits', $section)) ? $this->getNeededAcademicUnit($section["SectionAcademicUnits"]) : NULL;
        //if we don't have an associated unit we don't need this course, move on
        if (empty($unit)) continue;
        //get the sections semester term
        $semester = $entityTools->createOrGetTerm('semester', $section["AcademicPeriod_id"]);
        //set the semseter term for the course for later use
        $fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]['semester'] = $semester;
        $fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]['sections'][$section["CourseSection"]] = [
          'type' => 'course_sections',
          'field_section_course_id' => [
            'value' => $section["Course_id"],
          ],
          'field_section_dept_code' => [
            'value' => $unit,
          ],
          'field_section_number' => [
            'value' => $section["CourseSection"],
          ],
          'field_section_title' => [
            'value' => $section["Title"],
          ],
          'field_course_semester' => $semester, //@todo we need to move this so that we get the actual term number
        ];
        if (array_key_exists('InstructorRoleAssignments', $section)) {
          $fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]['sections'][$section["CourseSection"]]['field_section_instructors'] = $this->getSectionInstructors($section['InstructorRoleAssignments']);
        }
        if (array_key_exists('SectionComponents', $section)) {
          $fields[$unit][$section["AcademicPeriod_id"]][$section["Course_id"]]['sections'][$section["CourseSection"]]['field_room_schedules'] = $section['SectionComponents'];
        }
      }
    }
    return $fields;
  }

  /**
   * Gets the room schedule paragraph ids
   *
   * @param array $components
   *  the list of components to get schedules from
   *
   * @return array
   *  an array of the room schedule paragraph ids
   *
   * @throws
   */
  public function getRoomSchedules(array $components):array{

    $schedules = [];

    foreach ($components as $component) {
      if (array_key_exists('MeetingPattern_id',$component)){
        $schedules[] = $this->createRoomSchedule($component['MeetingPattern_id']);
      }
    }

    return $schedules;
  }

  /**
   * Creates the room schedule paragraphs and returns an array of their ids
   *
   * @param string $schedule
   *  the room schedule object
   *
   * @return array
   *  an array of the room schedule paragraph ids
   *
   * @throws
   */
  public function createRoomSchedule(string $schedule):array {
    //if there isn't a schedule return a empty array
    if (empty($schedule)){
      return [];
    }
    $scheduleParts = explode('_',$schedule);
    $days = $scheduleParts[0];

    //reformat the dateTime and clear out any values of 12:00AM
    $startTime = date('h:i A', strtotime($scheduleParts[1]));
    $startTime = ($startTime == "12:00 AM") ? "": $startTime;

    //reformat the dateTime and clear out any values of 12:00AM
    $endTime = date('h:i A', strtotime($scheduleParts[2])); //Endtime - time needs to be lowercase >:|
    $endTime = ($startTime == "12:00 AM") ? "": $endTime;

    //build the room_schedule paragraph
    $fields = [
      'type' => 'room_schedule',
      'field_day' => [
        'value'  => $days,
      ],
      'field_end_time' => [
        'value'  => $endTime,
      ],
      'field_start_time' => [
        'value'  => $startTime,
      ],
    ];
    $paragraph = \Drupal::service('washuas_courses.entitytools')->createContent($fields,'paragraph');

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

  /**
   * checks our academic units to make sure this section's department is selected for import
   *
   * @param array $units
   *  the list of academic units from the course
   *
   * @return string
   *  the academic unit we are processing this course for
   *
   * @throws
   */
  public function getNeededAcademicUnit(array $units):string{
    $activeUnits = \Drupal::service('config.factory')->get(static::SETTINGS)->get('courses_academic_units');
    foreach($units as $unit){
      if (isset($activeUnits[$unit["AcademicUnit_id"]])){
        return $unit["AcademicUnit_id"];
      }
    }
    return '';
  }

  /**
   * Gets paragraphs for course sections
   *
   * @param array $sections
   *  the sections we will create paragraphs for
   *
   * @return array
   *  an array of all the paragraph ids
   *
   * @throws
   */
  public function createSectionParagraphs(array $sections):array{
    $paragraphs = [];
    $entityTools = \Drupal::service('washuas_courses.entitytools');

    foreach ($sections as $section){
      //if the section is empty then unset it
      if (empty($section['field_room_schedules'])){
        unset($section['field_room_schedules']);
      }else{ //create the room schedule paragraphs
        $section['field_room_schedules'] = $this->getRoomSchedules($section['field_room_schedules']);
      }
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
   * @param array $courseAttributes
   *  the soap objects for course attributes
   *
   * @return array
   *  an array of all the paragraph ids
   *
   * @throws
   */
  public function getCourseAttributes(array $courseAttributes):array {

    $paragraphs = [];

    if (isset($courseAttributes)  && is_array($courseAttributes)) {
      $paragraphs = $this->createCourseAttributes($courseAttributes);
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
  protected function createCourseAttributes(array $courseAttributes):array {

    $paragraphs = [];
    $entityTools = \Drupal::service('washuas_courses.entitytools');

    foreach ( $courseAttributes as $attribute ) {
      $fields = [
        'type' => 'course_attributes',
        'field_ca_short_name' => [
          'value'  => $attribute['CourseTag_id'],
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
   * @param string $courseNum
   *  the soap objects for course attributes
   *
   * @return \Drupal\taxonomy\Entity\Term|array
   *  the taxonomy term for course level
   *
   * @throws
   */
  public function getCourseLevel(string $courseNum) {
    // Course Level is based on the first digit of the Course Code number
    switch(substr($courseNum, 0, 1)){
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
    $entityTools = \Drupal::service('washuas_courses.entitytools');
    $term = $entityTools->getTerm('course_level', $courseLevel);
    //return the pulled taxonomy term or the newly created one
    return (!$term)? $entityTools->createTerm('course_level', $courseLevel) : $term;
  }

  /**
   * Get the instructor names from the array
   *
   * @param array $instructorAssignments
   *  the instructors array
   *
   * @return string
   *  a comma delimited list of instructor last names
   *
   * @throws
   */
  public function getSectionInstructors(array $instructorAssignments):string {
    if (empty((array) $instructorAssignments)) {
      return '';
    }

    $names = [];
    foreach ($instructorAssignments as $role) {
      foreach($role['AssigneesToAdd'] as $instructor){
        $names[] = explode(',',$instructor['AssigneeName'])[0];
      }
    }
    return substr(implode(', ', $names), 0, 254);
  }

  /**
   * Reformat the display semester to integer for sorting purposes
   *
   * @param string $semester
   *  the display semester we'll reformat
   *
   * @return integer|FALSE
   *  the sort Semester
   *
   * @throws
   */
  function getSortSemester(string $semester):int {
    if(strlen($semester) != 6)
      return FALSE;

    $seasons = array(
      'Spring' => '02',
      'Summer' => '03',
      'Fall' => '05',
    );

    $components = explode('_',$semester);

    return isset($seasons[$components[0]]) ? intval($components[1].$seasons[$components[0]]) : FALSE;
  }

  /**
   * Get an initialized batch builder with our settings
   *
   * @param string $a
   *  the first value to be compared
   *
   * @param string $b
   *   the second value to be compared
   *   * @return int
   *  the value to use to sort with based on the comparison of a and b
   *
   * @throws
   */
  function sortSemesters(string $a, string $b) {
    //these get the integer value of the sort semester so we're able to sort correctly
    $sortA = $this->getSortSemester($a);
    $sortB = $this->getSortSemester($b);

    if ($sortA == $sortB) {
      return 0;
    }
    return ($sortA > $sortB) ? -1 : 1;
  }

  /**
   * Get an initialized batch builder with our settings
   *
   * @param string $title
   *  the title to display while batch processing
   *
   * @return BatchBuilder
   *  the initialized batch to add operations to and process
   *
   * @throws
   */
  public function initCoursesBatchBuilder(string $title):BatchBuilder{
    return (new BatchBuilder())
                ->setFile(\Drupal::service('module_handler')->getModule('washuas_courses')->getPath(). '/src/Services/Courses.php')
                ->setTitle(t($title))
                ->setFinishCallback([$this, 'coursesImportFinished'])
                ->setInitMessage(t('The courses import is starting'))
                ->setProgressMessage(t('Processed @current out of @total.'))
                ->setErrorMessage(t('The SOAP batch import has encountered an error'));
  }
  public function addMessageAndLog($message):void{
    $messenger = \Drupal::service('messenger')->addMessage($message);
    \Drupal::service('logger.factory')->get('washuas_courses')->notice($message);
  }
  /**
   * This processes the request data that we pulled during batch processing
   *
   * @param $success
   *  this is sent to the function via batch builder finish callback
   *
   * @param $results
   * this is sent to the function via batch builder finish callback
   *
   * @param $operations
   *  this is sent to the function via batch builder finish callback
   *
   */
  public function processMuleBatches($success, $results, $operations):void{
    foreach ($results['data'] as $function => $data) {
      //condense the multiple arrays into one array
      $dataArray = array_merge($data);
      //take an action depending on what the function is
      switch($function){
        case 'sections':
          $sections = $this->processSections($dataArray);
          break;
        case 'courses':
          $test = 2;
          break;
        default:
          break;
      }
    }
  }
  /**
   * Get an initialized batch builder with our settings
   *
   * @param string $title
   *  the title to display while batch processing
   *
   * @return BatchBuilder
   *  the initialized batch to add operations to and process
   *
   * @throws
   */
  public function initSectionsBatchBuilder():BatchBuilder{
    return (new BatchBuilder())
      ->setFile(\Drupal::service('module_handler')->getModule('washuas_courses')->getPath(). '/src/Services/Courses.php')
      ->setTitle(t('Pulling Section API data from mulesoft in batches of 100.'))
      ->setFinishCallback([\Drupal::service('washuas_courses.courses'), 'processMuleBatches'])
      ->setInitMessage(t('The request is starting to import sections from mulesoft'))
      ->setProgressMessage(t('Processed requests @current out of @total.'))
      ->setErrorMessage(t('The sections api pull from mulesoft for has encountered an error'));
  }
}
