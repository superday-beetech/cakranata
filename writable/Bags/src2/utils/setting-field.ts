
import {
    SETTING_FIELDS,
    type SettingField,
    type SettingInputType,
} from "@/config/setting-fields";

type SettingDataType =
    | "string"
    | "integer"
    | "float"
    | "boolean"
    | "json";

export function getSettingField(
    className: string,
    property: string,
    dataType: SettingDataType,
): SettingField {
    const normalizedClass =
        className.split("\\").filter(Boolean).pop() ?? className;

    const customField =
        SETTING_FIELDS[className]?.[property] ??
        SETTING_FIELDS[normalizedClass]?.[property];

    if (customField) {
        return customField;
    }

    const defaultInputs: Record<SettingDataType, SettingInputType> = {
        string: "text",
        integer: "number",
        float: "number",
        boolean: "switch",
        json: "json",
    };

    return {
        input: defaultInputs[dataType],
        label: property,
    };
}
