<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Validation_Service {

    /**
     * Ensure a required field exists and is not empty.
     *
     * @param array  $data
     * @param string $field
     * @return true|WP_Error
     */
    public static function required($data, $field) {

        if (
            !isset($data[$field]) ||
            (is_string($data[$field]) && trim($data[$field]) === '')
        ) {

            return new WP_Error(
                'yac_validation_error',
                "{$field} is required.",
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate email.
     *
     * @param string $email
     * @return true|WP_Error
     */
    public static function email($email) {

        if (!is_email($email)) {

            return new WP_Error(
                'yac_validation_error',
                'Invalid email address.',
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate numeric value.
     *
     * @param mixed $value
     * @return true|WP_Error
     */
    public static function numeric($value) {

        if (!is_numeric($value)) {

            return new WP_Error(
                'yac_validation_error',
                'Value must be numeric.',
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate positive number.
     *
     * @param mixed $value
     * @return true|WP_Error
     */
    public static function positive_number($value) {

        if (!is_numeric($value) || $value <= 0) {

            return new WP_Error(
                'yac_validation_error',
                'Value must be greater than zero.',
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate date.
     *
     * Expected format: YYYY-MM-DD
     *
     * @param string $date
     * @return true|WP_Error
     */
    public static function date($date) {

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {

            return new WP_Error(
                'yac_validation_error',
                'Invalid date format. Expected YYYY-MM-DD.',
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate enum.
     *
     * @param mixed $value
     * @param array $allowed
     * @return true|WP_Error
     */
    public static function enum($value, array $allowed) {

        if (!in_array($value, $allowed, true)) {

            return new WP_Error(
                'yac_validation_error',
                'Invalid value.',
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

    /**
     * Validate maximum string length.
     *
     * @param string $value
     * @param int    $length
     * @return true|WP_Error
     */
    public static function max_length($value, $length) {

        if (mb_strlen($value) > $length) {

            return new WP_Error(
                'yac_validation_error',
                "Maximum length is {$length} characters.",
                [
                    'status' => 400,
                ]
            );

        }

        return true;

    }

}