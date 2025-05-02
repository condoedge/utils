<?php

if (!function_exists('getTableInputValues')) {
    /**
     * When we use an input in a table, almost every time we set the name of the input suffixing the record id
     * This function will return the parsed value in an array format
     * @param mixed $prefix The input name (without the id suffix)
     * @return array The parsed value of the input
     */
    function getTableInputValues($prefix, $ids = null) {
        $values = [];
        $requestData = request()->all();

        foreach ($requestData as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $id = str_replace($prefix, '', $key);

                if (is_array($ids) && !in_array($id, $ids)) {
                    continue;
                }

                $values[$id] = $value;
            }
        }

        return $values;
    }
}
