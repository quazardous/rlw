<?php
namespace RLW\Webservice;

class WebserviceException extends \Exception {
  const loop_in_required_subrequests = 1;
  const unknown_subrequest_tag = 2;
  const invalid_subrequest_tag = 3;
  const unknown_subrequest_name = 4;
  const request_inconsistency = 5;
  const validation_errors = 6;
  const logic_inconsistency = 7;
  const unknown_request_parameter_type = 8;
  const no_nested_type = 9;
  const no_tags = 10;
  const no_struct = 11;
  const unknown_request_parameter_custom_type = 12;
}