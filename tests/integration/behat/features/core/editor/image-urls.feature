Feature: Editor image urls

  Background:
    Given I am logged in as a regular user

  Scenario: Requesting image urls for multiple images returns all of them
    Given I have a "png" image file in my home folder
    And I have a "png" image file in my home folder
    When I request image urls for the files
    Then the image urls request should succeed
    And the response should contain 2 images

  Scenario: Requesting image urls skips files that don't exist and keeps the valid ones
    Given I have a "png" image file in my home folder
    When I request image urls including a file that does not exist
    Then the image urls request should succeed
    And the response should contain 1 image
