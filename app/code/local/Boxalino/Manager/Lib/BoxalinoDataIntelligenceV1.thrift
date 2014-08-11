namespace java com.boxalino.dataintelligence.api.thrift
namespace php com.boxalino.dataintelligence.api.thrift

/**
 * This enumeration defines the possible exception states returned by Boxalino Data Intelligence Thrift API
 *
 * <dl>
 * <dt>GENERAL_EXCEPTION</dt>
 * <dd>general case of exception (no special detailed provided)</dd>
 *
 * <dt>INVALID_CREDENTIALS</dt>
 * <dd>the provided credentials to retrieve an authentication token are not valid (wrong username, password or both)</dd>
 *
 * <dt>BLOCKED_USER</dt>
 * <dd>your user has been blocked (but it doesn't necessarily mean your account has been blocked)</dd>
 *
 * <dt>BLOCKED_ACCOUNT</dt>
 * <dd>your account has been blocked, you must contact Boxalino (<a href="mailto:support@boxalino.com">support@boxalino.com</a>) to know the reasons of this blocking.</dd>
 *
 * <dt>INVALID_AUTHENTICATION_TOKEN</dt>
 * <dd>the provided authentication token is invalid (wrong, or no more valid), you should get a new one by calling the GetAuthentication service.</dd>
 *
 * <dt>INVALID_NEW_PASSWORD</dt>
 * <dd>specific to the service function UpdatePassword: means that the new password is not correct (should be at least 8 characters long and not contain any punctuation)</dd>
 * 
 * <dt>INVALID_CONFIGURATION_VERSION</dt>
 * <dd>the provided configuration object contains a configuration version number which doesn't exists or cannot be accessed</dd>
 * </dl>
 * 
 * <dt>INVALID_data source</dt>
 * <dd>the provided XML data source is not correct (see documentation of the data source XML format)</dd>
 * </dl>
 * 
 * <dt>NON_EXISTING_CONTENT_ID</dt>
 * <dd>the provided content to be changed (updated, deleted, etc.) is defined with a content id which doesn't exists</dd>
 * </dl>
 * 
 * <dt>ALREADY_EXISTING_CONTENT_ID</dt>
 * <dd>the provided content id to be created already exists</dd>
 * </dl>
 * 
 * <dt>INVALID_CONTENT_ID</dt>
 * <dd>the provided content id doesn't not match the requested format (less than 50 alphanumeric characters without any punctuation or accent)</dd>
 * </dl>
 * 
 * <dt>INVALID_CONTENT</dt>
 * <dd>the provided content data are not correctly set</dd>
 * </dl>
 * 
 * <dt>INVALID_LANGUAGE</dt>
 * <dd>one of the provided languages has not been defined for this account</dd>
 * </dl>
 */
enum DataIntelligenceServiceExceptionNumber {
	GENERAL_EXCEPTION = 1,
	INVALID_CREDENTIALS = 2,
	BLOCKED_USER = 3,
	BLOCKED_ACCOUNT = 4,
	INVALID_AUTHENTICATION_TOKEN = 5,
	INVALID_NEW_PASSWORD = 6,
	INVALID_CONFIGURATION_VERSION = 7,
	INVALID_data source = 8,
	NON_EXISTING_CONTENT_ID = 9,
	ALREADY_EXISTING_CONTENT_ID = 10,
	INVALID_CONTENT_ID = 11,
	INVALID_CONTENT = 12,
	INVALID_LANGUAGE = 13
}

/**
 * This exception is raised by all the BoxalinoDataIntelligence service function in case of a problem
 *
 * <dl>
 * <dt>exceptionNumber</dt>
 * <dd>indicate the exception number based on the enumeration DataIntelligenceServiceExceptionNumber</dd>
 * <dt>message</dt>
 * <dd>a textual message to explain the error conditions more in details</dd>
 * </dl>
 */
exception DataIntelligenceServiceException {
	1: required DataIntelligenceServiceExceptionNumber exceptionNumber
	2: required string message
}

/**
 * This structure defines the parameters to be send to receive an authentication token (required by all the other services)
 *
 * <dl>
 * <dt>account</dt>
 * <dd>	the name of your account (as provided to you by Boxalino team, if you don't have an account, contact <a href="mailto:support@boxalino.com">support@boxalino.com</a>)</dd>
 * <dt>username</dt>
 * <dd>	usually the same value as account (but can be different for users with smaller rights, if you don't have a username, contact <a href="mailto:support@boxalino.com">support@boxalino.com</a>)</dd>
 * <dt>password</dt>
 * <dd>	as provided by Boxalino, or according to the last password update you have set. If you lost your password, contact <a href="mailto:support@boxalino.com">support@boxalino.com</a>)</dd>
 * </dl>
 */
struct AuthenticationRequest {
	1: required string account,
	2: required string username,
	3: required string password
}

/**
 * This structure defines the authentication object (to pass as authentication proof to all function and services)
 *
 * <dl>
 * <dt>authenticationToken</dt>
 * <dd>the return authentication token is a string valid for one hour</dd>
 * </dl>
 */
struct Authentication {
	1: required string authenticationToken
}

/**
 * This enumeration defines the version type. All contents are versioned, normally, you want to change the current development version and then, when finished, publish it (so it becomes the new production version and a new development version is created), but it is also possible to access the production version directly
 *
 * <dl>
 * <dt>CURRENT_DEVELOPMENT_VERSION</dt>
 * <dd>this is the normal case, as you want to retrieve the current dev version of your account configuration and not touch the production one</dd>
 *
 * <dt>CURRENT_PRODUCTION_VERSION</dt>
 * <dd>this should only be used in rare cases where you want to recuperate information from the production configuration, but be careful in changing this version as it will immediately affect your production processes!</dd>
 * </dl>
 */
enum ConfigurationVersionType {
	CURRENT_DEVELOPMENT_VERSION = 1,
	CURRENT_PRODUCTION_VERSION = 2,
}

/**
 * This structure defines a configuration version of your account. It must be provided to all functions accessing / updating or removing information from your account configuration
 *
 * <dl>
 * <dt>configurationVersionNumber</dt>
 * <dd>an internal number identifying the configuration version</dd>
 * </dl>
 */
struct ConfigurationVersion {
	1: required i16 configurationVersionNumber
}

/**
 * This structure defines a configuration difference (somethin which has changed between two configuration versions)
 *
 * <dl>
 * <dt>contentType</dt>
 * <dd>the type of content which has changed (e.g.: 'field')</dd>
 * <dt>contentId</dt>
 * <dd>the content id which has changed (e.g: a field id)</dd>
 * <dt>parameterName</dt>
 * <dd>the content parameter which has changed (e.g.: a field type)</dd>
 * <dt>contentSource</dt>
 * <dd>the string encoded value of the content parameter value of the source configuration</dd>
 * <dt>contentDestination</dt>
 * <dd>the string encoded value of the content parameter value of the destination configuration</dd>
 * </dl>
 */
struct ConfigurationDifference {
	1: required string contentType,
	2: required string contentId,
	3: required string parameterName,
	4: required string contentSource,
	5: required string contentDestination
}

/**
 * This structure defines a data Field. A field covers any type of data property (customer property, product properties, etc.). Fields are global for all data sources, but can be used only for special data sources and ignored for others. This grants that the properties are always ready to unify values from different sources, but they don't have to.
 *
 * <dl>
 * <dt>fieldId</dt>
 * <dd>a unique id which should not contain any punctuation, only non-accentuated alphabetic and numeric characters and should not be longer than 50 characters</dd>
 * </dl>
 */
struct Field {
	1: required string fieldId
}

/**
 * This structure defines a data ProcessTask. A process task covers any kind of process task to be executed by the system.
 *
 * <dl>
 * <dt>processTaskId</dt>
 * <dd>a unique id which should not contain any punctuation, only non-accentuated alphabetic and numeric characters and should not be longer than 50 characters</dd>
 * </dl>
 */
struct ProcessTask {
	1: required string processTaskId
}

enum Language {
	GERMAN = 1,
	FRENCH = 2,
	ENGLISH = 3,
	ITALIAN = 4,
	SPANISH = 5,
	DUTCH = 6,
	PORTUGUESE = 7,
	SWIDISH = 8,
	ARABIC = 9,
	RUSSIAN = 10,
	JAPANESE = 11,
	KOREAN = 12,
	TURKISH = 13,
	VIETNAMESE = 14,
	POLISH = 15,
	UKRAINIAN = 16,
	CHINESE_MANDARIN = 17,
	OTHER = 100
}

/**
 * This structure defines a data EmailCampaign. A campaign is a parameter holder for a campaign execution. It should not change at each sending, but the parameters (especially cmpid) can be
 * (should) be changed before any new campaign sending (if new campid applies). For the case of trigger campaigns, the cmpid (and other parameters) usually don't change, but for the case of newsletter campaigns, very often each sending has a different id. In this case, the cmpid must be updated (and the dev configuration should be published) every time.
 * <dl>
 * <dt>campaignId</dt>
 * <dd>a unique id which should not contain any punctuation, only non-accentuated alphabetic and numeric characters and should not be longer than 50 characters</dd>
 * <dt>cmpid</dt>
 * <dd>the running campaign id which is often specific to the running of a specific newsletter e-mail (should be changed every time before sending a blast e-mail with the new value (don't forget to publish the dev configuration)</dd>
 * <dt>dateTime</dt>
 * <dd>the dateTime at which the campaign will be sent (cannot be in the past when the campaign is ran, an exception will be then raised). must have the format (Y-m-d H:i:s)</dd>
 * <dt>baseUrl</dt>
 * <dd>a localized value of the base url to use for e-mail links</dd>
 * <dt>subject</dt>
 * <dd>a localized value of the subject line of the e-mail (default, can be overwritten by a specific choice variant localized parameters with parameter name 'subject')</dd>
 * <dt>firstSentence</dt>
 * <dd>a localized value of the first sentence of the e-mail (default, can be overwritten by a specific choice variant localized parameters with parameter name 'firstSentence')</dd>
 * <dt>legals</dt>
 * <dd>a localized value of the legal notices to be included in the e-mail (default, can be extended by a specific choice variant localized parameters with parameter name 'legals')</dd>
 * </dl>
 */
struct EmailCampaign {
	1: required string emailCampaignId
	2: required string cmpid
	3: required string dateTime
	4: required map<Language,string> baseUrl
	5: required map<Language,string> subject
	6: required map<Language,string> firstSentence
	7: required map<Language,string> legals
}

/**
 * This structure defines a data Choice.
 * <dl>
 * <dt>choiceId</dt>
 * <dd>a unique id which should not contain any punctuation, only non-accentuated alphabetic and numeric characters and should not be longer than 50 characters</dd>
 * </dl>
 */
struct Choice {
	1: required string choiceId
}

/**
 * This structure defines a data Choice variant
 * <dl>
 * <dt>choiceVariantId</dt>
 * <dd>a unique id which should not contain any punctuation, only non-accentuated alphabetic and numeric characters and should not be longer than 50 characters</dd>
 * <dt>choiceId</dt>
 * <dd>the choice id of the choice which this variant is associated to</dd>
 * <dt>tags</dt>
 * <dd>a list of tags this variant is connected to</dd>
 * <dt>simpleParameters</dt>
 * <dd>a list of non-localized parameters this variant is connected to (for example, to overwrite the campaign properties, keys should have the same name as the campaign parameter name)</dd>
 * <dt>localizedParemeters</dt>
 * <dd>a list of localized parameters this variant is connected to (for example, to overwrite the campaign properties, keys should have the same name as the campaign parameter name)</dd>
 * </dl>
 */
struct ChoiceVariant {
	1: required string choiceVariantId,
	2: required string choiceId,
	3: required list<string> tags,
	4: required map<string, list<string>> simpleParameters,
	5: required map<string, list<map<Language,string>>> localizedParemeters
}

/**
 * This enumeration defines the possible process task execution statuses type (to check the completion of an execution of  process task and its result)
 *
 * <dl>
 * <dt>WAITING</dt>
 * <dd>The process was not started yet</dd>
 *
 * <dt>STARTED</dt>
 * <dd>The process has started and is currently running</dd>
 * </dl>
 *
 * <dt>STARTED</dt>
 * <dd>The process has started and is currently running</dd>
 * </dl>
 *
 * <dt>FINISHED_SUCCESS</dt>
 * <dd>The process has finished successfully</dd>
 * </dl>
 *
 * <dt>FINISHED_WITH_WARNINGS</dt>
 * <dd>The process has finished, but with some warnings</dd>
 * </dl>
 *
 * <dt>FAILED</dt>
 * <dd>The process has failed</dd>
 * </dl>
 *
 * <dt>ABORTED</dt>
 * <dd>The process has been aborted</dd>
 * </dl>
 */
enum ProcessTaskExecutionStatusType {
	WAITING = 1,
	STARTED = 2,
	FINISHED_SUCCESS = 3,
	FINISHED_WITH_WARNINGS = 4,
	FAILED = 5,
	ABORTED = 6,
}

/**
 * This structure defines a process task execution status (the status of execution of a process task) with its type and a textual message
 *
 * <dl>
 * <dt>statusType</dt>
 * <dd>the status type of this execution of the process task</dd>
 * <dt>information</dt>
 * <dd>some additonal information about the type (can be empty, used to explain errors and warnings)</dd>
 * </dl>
 */
struct ProcessTaskExecutionStatus {
	1: required ProcessTaskExecutionStatusType statusType,
	2: required string information
}

/**
 * This structure defines a process task execution parameters
 *
 * <dl>
 * <dt>processTaskId</dt>
 * <dd>the process task id to execute</dd>
 * <dt>development</dt>
 * <dd>should the process run with dev version data</dd>
 * <dt>delta</dt>
 * <dd>is the process an incremental process (or full)</dd>
 * <dt>forceStart</dt>
 * <dd>if another similar process is already running, the forceStart will make the new one run, otherwise, the execution will be aborted</dd>
 * </dl>
 */
struct ProcessTaskExecutionParameters {
	1: required string processTaskId,
	2: required bool development,
	3: required bool delta,
	4: required bool forceStart
}


service BoxalinoDataIntelligence {
/**
 * this service function returns a new authentication token
 *
 * <dl>
 * <dt>@param authentication</dt>
 * <dd>a fully complete AuthenticationRequest with the content of your credentials</dd>
 * <dt>@return</dt>
 * <dd>an Authentication object with your new authentication token (valid for 1 hour)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_CREDENTIALS:if the provided account / username / password information don't match the records of Boxalino system.</dd>
 * <dd>BLOCKED_USER:if the provided user has been blocked.</dd>
 * <dd>BLOCKED_ACCOUNT:if the provided account has been blocked.</dd>
 * </dl>
 */
	Authentication GetAuthentication(1: AuthenticationRequest authentication) throws (1: DataIntelligenceServiceException e),
	
	
/**
 * this service function changes the current password
 *
 * <dl>
 * <dt>@param authentication</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param newPassword</dt>
 * <dd>the new password to replace the existing one (careful, no forgot the new password, if you lose your password, contact <ahref="mailto:support@boxalino.com">support@boxalino.com</a></dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_NEW_PASSWORD:if the provided new password is not properly formatted (should be at least 8 characters long and not contain any punctuation).</dd>
 * </dl>
 */
	void UpdatePassword(1:	Authentication authentication, 2: string newPassword) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the configuration version object matching the provided versionType. In general, you should always ask for the CURRENT_DEVELOPMENT_VERSION, unless you want to access directly (at your own risks) the production configuration.
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN: if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dt>@returns ConfigurationVersion</dt>
 * <dd>The configuration object to use in your calls to other service functions which access your configuration parameters</dd>
 * </dl>
 */
	ConfigurationVersion GetConfigurationVersion(1: Authentication authentication, 2: ConfigurationVersionType versionType) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function udpates your data source configuration.
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param data sourcesConfigurationXML</dt>
 * <dd>the data source XML must follow the strict XML format and content as defined in the Boxalino documentation. This XML defines the way the system must extract data from the various files (typically a list of CSV files compressed in a zip file) to synchronize your product, customers and transactions data (tracker data are direclty provided to Boxalino Javascript tracker and are there not part of the data to be synchronized here. Please make sure that the product id is defined in a coherent way between he product files, the transaction files and the tracker (product View, add to basket and purchase event) (so the mapping can be done); same comment for the customer id: between the customer files, the transaction files and the tracker (set user event). If you don't have the full documentation of the data source XML, please contact <a href="mailto:support@boxalino.com">support@boxalino.com</a></dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>INVALID_data source:if the provided new data source XML string doesn't match the required format (see documentation of the data source XML format)</dd>
 * <dt>@Nota Bene</dt>
 * <dd>If you remove fields definition from your data source, they will not be automatically deleted. You need to explicitely delte them through the delete component service function to remove them.</dd>
 * </dl>
 */
	void SetDataSourcesConfiguration(1: Authentication authentication, 2: ConfigurationVersion configurationVersion, 3: string dataSourcesConfigurationXML) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the map of all the defined field (key = fieldId, value = Field object).
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dt>@returns map<string, Field></dt>
 * <dd>A map containing all the defined fields of your account in this configuration version, with the fieldId as key and the Field object as value (key is provided for accessibility only, as the field id is also present in the Field object</dd>
 * </dl>
 */
	map<string, Field> GetFields(1: Authentication authentication, 2: ConfigurationVersion configuration) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function creates a new field 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param fieldId</dt>
 * <dd>the field id to be created (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>ALREADY_EXISTING_CONTENT_ID:if the provided field id already exists.</dd>
 * <dd>INVALID_CONTENT_ID:if the provided field id format is not valid.</dd>
 * </dl>
 */
	void CreateField(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string fieldId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function updates a field 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param field</dt>
 * <dd>a Field object to be updated (the content of the object will be updated on the content id provided)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided field id doesn't already exists.</dd>
 * <dd>INVALID_CONTENT:if the provided field content is not valid.</dd>
 * <dd>The </dd>
 * </dl>
 */
	void UpdateField(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: Field field) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function deletes a field
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param fieldId</dt>
 * <dd>the field id to be deleted</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided field id doesn't already exists.</dd>
 * </dl>
 */
	void DeleteField(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string fieldId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the map of all the defined process tasks (key = processTaskId, value = ProcessTask object).
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dt>@returns map<string, ProcessTask></dt>
 * <dd>A map containing all the defined process tasks of your account in this configuration version, with the processTaskId as key and the ProcessTask object as value (key is provided for accessibility only, as the processTaskId is also present in the ProcessTask object</dd>
 * </dl>
 */
	map<string, ProcessTask> GetProcessTasks(1: Authentication authentication, 2: ConfigurationVersion configuration) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function creates a new process task 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param processTaskId</dt>
 * <dd>the process task id to be created (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>ALREADY_EXISTING_CONTENT_ID:if the provided process task id already exists.</dd>
 * <dd>INVALID_CONTENT_ID:if the provided process task id format is not valid.</dd>
 * </dl>
 */
	void CreateProcessTask(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string processTaskId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function updates a process task 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param processTask</dt>
 * <dd>a ProcessTask object to be updated (the content of the object will be updated on the content id provided)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided process task id doesn't already exists.</dd>
 * <dd>INVALID_CONTENT:if the provided process task content is not valid.</dd>
 * <dd>The </dd>
 * </dl>
 */
	void UpdateProcessTask(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: ProcessTask processTask) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function deletes a process task
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param processTaskId</dt>
 * <dd>the process task id to be deleted</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided process task id doesn't already exists.</dd>
 * </dl>
 */
	void DeleteProcessTask(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string processTaskId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function executes a process task
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param processTaskId</dt>
 * <dd>the process task id to be executed</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided process task id doesn't already exists.</dd>
 * <dt>@return process id</dt>
 * <dd>the processs task execution id of this process task execution (to be used to get an updated status through GetProcessStatus)</dd>
 * </dl>
 */
	string RunProcessTask(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: ProcessTaskExecutionParameters parameters) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the map of all the defined email campaigns (key = emailCampaignId, value = EmailCampaign object).
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dt>@returns map<string, EmailCampaign></dt>
 * <dd>A map containing all the defined email campaigns of your account in this configuration version, with the emailCampaignId as key and the EmailCampaign object as value (key is provided for accessibility only, as the emailCampaignId is also present in the EmailCampaign object</dd>
 * </dl>
 */
	map<string, EmailCampaign> GetEmailCampaigns(1: Authentication authentication, 2: ConfigurationVersion configuration) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function creates a new email campaign. a campaign is something permanent , so you shouldn't create one for each sending of a newsletter (but instead update the cmpid parameter of a permanent campaign e.g.: 'newsletter')
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param emailCampaignId</dt>
 * <dd>the email campaign id to be created (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>ALREADY_EXISTING_CONTENT_ID:if the provided email campaign id already exists.</dd>
 * <dd>INVALID_CONTENT_ID:if the provided email campaign id format is not valid.</dd>
 * </dl>
 */
	void CreateEmailCampaign(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string emailCampaignId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function updates a email campaign 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param emailCampaign</dt>
 * <dd>a EmailCampaign object to be updated (the content of the object will be updated on the content id provided)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided email campaign id doesn't already exists.</dd>
 * <dd>INVALID_CONTENT:if the provided email campaign content is not valid.</dd>
 * <dd>The </dd>
 * </dl>
 */
	void UpdateEmailCampaign(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: EmailCampaign emailCampaign) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function deletes a email campaign
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param emailCampaignId</dt>
 * <dd>the email campaign id to be deleted</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided email campaign id doesn't already exists.</dd>
 * </dl>
 */
	void DeleteEmailCampaign(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string emailCampaignId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the map of all the defined choices (key = choiceId, value = Choice object).
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param choiceSourceId</dt>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dt>@returns map<string, Choice></dt>
 * <dd>A map containing all the defined choices of your account in this configuration version, with the choiceId as key and the Choice object as value (key is provided for accessibility only, as the choiceId is also present in the Choice object</dd>
 * </dl>
 */
	map<string, Choice> GetChoices(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function creates a new choice
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice id to be created (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>ALREADY_EXISTING_CONTENT_ID:if the provided choice id already exists.</dd>
 * <dd>INVALID_CONTENT_ID:if the provided choice id format is not valid.</dd>
 * </dl>
 */
	void CreateChoice(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: string choiceId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function updates a choice 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choice</dt>
 * <dd>a Choice object to be updated (the content of the object will be updated on the content id provided)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice id doesn't already exists.</dd>
 * <dd>INVALID_CONTENT:if the provided choice content is not valid.</dd>
 * <dd>The </dd>
 * </dl>
 */
	void UpdateChoice(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: Choice choice) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function deletes a choice
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice id to be deleted</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice id doesn't already exists.</dd>
 * </dl>
 */
	void DeleteChoice(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: string choiceId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function returns the map of all the defined choices (key = choiceId, value = Choice object).
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice id on which to get the choice variants from</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice id doesn't already exists.</dd>
 * <dt>@returns map<string, Choice></dt>
 * <dd>A map containing all the defined choice variants of your account in this configuration version and for this specific choice, with the choiceVariantId as key and the ChoiceVariant object as value (key is provided for accessibility only, as the choiceVariantId is also present in the ChoiceVariant object</dd>
 * </dl>
 */
	map<string, ChoiceVariant> GetChoiceVariants(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: string choiceId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function creates a new choice
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice id on which to create a new choice variant (must exists)</dd>
 * <dt>@param choiceVariantId</dt>
 * <dd>the choice variant id to be created (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice id doesn't already exists.</dd>
 * <dd>ALREADY_EXISTING_CONTENT_ID:if the provided choice variant id already exists.</dd>
 * <dd>INVALID_CONTENT_ID:if the provided choice variant id format is not valid.</dd>
 * </dl>
 */
	void CreateChoiceVariant(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: string choiceId, 5: string choiceVariantId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function updates a choice 
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceVariant</dt>
 * <dd>a ChoiceVariant object to be updated (the content of the object will be updated on the content id provided)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice variant id doesn't already exists.</dd>
 * <dd>INVALID_CONTENT:if the provided choice variant content is not valid.</dd>
 * <dd>The </dd>
 * </dl>
 */
	void UpdateChoiceVariant(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: ChoiceVariant choiceVariant) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function deletes a choice
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dd>the choice source id (identifying the system being the source of the choices, if you don't have a choice source id already, please contact support@boxalino.com) (must follow the content id format: <= 50 alphanumeric characters without accent or punctuation)</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice id on which to delete the choice variant id</dd>
 * <dt>@param choiceId</dt>
 * <dd>the choice variant id to be deleted</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * <dd>NON_EXISTING_CONTENT_ID:if the provided choice or choice variant id doesn't already exists.</dd>
 * </dl>
 */
	void DeleteChoiceVariant(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string choiceSourceId, 4: string choiceId, 5: string choiceVariantId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service function retrieves a process status
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@param processTaskExecutionId</dt>
 * <dd>the process task execution status id to retrieve the status of</dd>
 * <dt>@return process task execution status</dt>
 * <dd>the current status of this process task execution id</dd>
 * </dl>
 */
	ProcessTaskExecutionStatus GetProcessStatus(1: Authentication authentication, 2: ConfigurationVersion configuration, 3: string processTaskExecutionId) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service retrieves the list of configuration changes between two versions (typically between dev and prod versions)
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersionSource</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion) to be considered as the source (typically the version returned by GetConfigurationVersion with the ConfigurationVersionType CURRENT_DEVELOPMENT_VERSION)</dd>
 * <dt>@param configurationVersionDestination</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion) to be considered as the destination (typically the  version returned by GetConfigurationVersion with the ConfigurationVersionType CURRENT_PRODUCTION_VERSION)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if one of provided configuration versions is not valid.</dd>
 * </dl>
 */
	list<ConfigurationDifference> GetConfigurationDifferences(1: Authentication authentication, 2: ConfigurationVersion configurationVersionSource, 3: ConfigurationVersion configurationVersionDestination) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service retrieves publishes the provided configuration version. The result is that this configuration will become the CURRENT_PRODUCTION_VERSION version and will be used automatically by all running processes. Also, as a consequence, a copy of the provided configuration version will be done and will become the new CURRENT_DEVELOPMENT_VERSION.
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * </dl>
 */
	void PublishConfiguration(1: Authentication authentication, 2: ConfigurationVersion configuration) throws (1: DataIntelligenceServiceException e),
	
/**
 * this service retrieves copies the provided configuration version. The result is that this new configuration will become the CURRENT_DEVELOPMENT_VERSION.
 *
 * <dl>
 * <dt>@param authenticationToken</dt>
 * <dd>the authentication object as returned by the GetAuthentication service function in the AuthenticationResponse struct</dd>
 * <dt>@param configurationVersion</dt>
 * <dd>a ConfigurationVersion object indicating the configuration version number (as returned by function GetConfigurationVersion)</dd>
 * <dt>@throws DataIntelligenceServiceException</dt>
 * <dd>INVALID_AUTHENTICATION_TOKEN:if the provided authentication token is not valid or has expired (1 hour validity).</dd>
 * <dd>INVALID_CONFIGURATION_VERSION: if the provided configuration version is not valid.</dd>
 * </dl>
 */
	void CloneConfiguration(1: Authentication authentication, 2: ConfigurationVersion configuration) throws (1: DataIntelligenceServiceException e)
}
