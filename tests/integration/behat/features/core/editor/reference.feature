Feature: Editor reference

  Background:
    Given I am logged in as a regular user
    And I have a "docx" file in my home folder

  Scenario: Resolving a file by path returns its reference data
    When I resolve the file by path
    Then the reference should be resolved successfully
    And the response should contain reference data with a file key and instance id

  Scenario: Resolving a file by its reference data returns file info
    Given I have already resolved the file by path
    When I resolve the file by its reference data
    Then the reference should be resolved successfully

  Scenario: Resolving a non-existent file returns an error
    When I resolve a file by a path that does not exist
    Then the reference should not be found
