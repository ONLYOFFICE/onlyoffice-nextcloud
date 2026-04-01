Feature: Editor config

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Requesting config for a supported document returns the editor configuration
    When I request the editor config for the file
    Then the config should be returned successfully
    And the config should contain document and editor settings
    And the document type should be "word"

  Scenario: Requesting config for an unsupported file format returns an error
    Given I have a "xyz" file in my home folder
    When I request the editor config for the file
    Then the config request should fail with an unsupported format error

  Scenario: Requesting config for a non-existent file returns an error
    When I request the editor config for a file that does not exist
    Then the config request should fail
