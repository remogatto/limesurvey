package limesurvey

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"net/http"
)

var (
	commandID int
)

type Command struct {
	Id     int           `json:"id"`
	Method string        `json:"method"`
	Params []interface{} `json:"params"`
}

type Result struct {
	Id     int
	Result interface{}
	Error  string
}

type Participant struct {
	Firstname  string
	Lastname   string
	Email      string
	Token      string
	Attributes map[string]interface{}
}

type UploadedFile struct {
	Filename string
	Content  []uint8
}

type LSAPI struct {
	Url        string
	SessionKey string
}

func (c *Command) Execute(url string) (*Result, error) {
	commandID++
	c.Id = commandID
	b, err := json.Marshal(c)

	if err != nil {
		return nil, err
	}
	req, err := http.NewRequest("POST", url, bytes.NewBuffer(b))
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-Custom-Header", "myvalue")
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	body, _ := ioutil.ReadAll(resp.Body)

	result := &Result{}
	err = json.Unmarshal(body, result)
	if err != nil {
		return nil, fmt.Errorf("Error during JSON unmarshaling. The raw response body was %s. The error is %s", body, err)
	}
	if m, ok := result.Result.(map[string]interface{}); ok {
		if m["status"] != nil {
			if errMsg := m["status"].(string); errMsg != "" {
				return nil, fmt.Errorf("%s", result.Error)
			}
		}

	}
	if result.Error != "" {
		return nil, fmt.Errorf("%s", result.Error)
	}
	return result, nil
}

func New(url, username, password string) (*LSAPI, error) {
	getSessionKey := &Command{
		Method: "get_session_key",
		Params: []interface{}{username, password},
	}
	key, err := getSessionKey.Execute(url)
	if err != nil {
		return nil, err
	}
	return &LSAPI{
		Url:        url,
		SessionKey: key.Result.(string),
	}, nil
}

func (api *LSAPI) ExportResponses(surveyID int, docType string, options ...interface{}) (string, error) {
	exportResponses := &Command{
		Method: "export_responses",
		Params: append(
			[]interface{}{
				api.SessionKey,
				surveyID,
				docType,
			}, options...),
	}
	result, err := exportResponses.Execute(api.Url)
	if err != nil {
		return "", err
	}
	return result.Result.(string), nil
}

func (api *LSAPI) ListParticipants(surveyID int, options ...interface{}) (map[string]*Participant, error) {
	cmd := &Command{
		Method: "list_participants",
		Params: append(
			[]interface{}{
				api.SessionKey,
				surveyID,
			}, options...),
	}
	result, err := cmd.Execute(api.Url)
	if err != nil {
		return nil, err
	}

	participants := make(map[string]*Participant)
	results := result.Result.([]interface{})
	attrNames := make([]string, 0)
	for _, r := range results {
		attributes := make(map[string]interface{})
		token := r.(map[string]interface{})["token"].(string)
		pInfo := r.(map[string]interface{})["participant_info"]
		if len(options) > 3 {
			if v, ok := options[3].([]string); ok {
				attrNames = v
			}
		}
		for _, name := range attrNames {
			attributes[name] = r.(map[string]interface{})[name]

		}
		participants[token] = &Participant{
			Firstname:  pInfo.(map[string]interface{})["firstname"].(string),
			Lastname:   pInfo.(map[string]interface{})["lastname"].(string),
			Email:      pInfo.(map[string]interface{})["email"].(string),
			Token:      token,
			Attributes: attributes,
		}
	}

	return participants, nil
}

func (api *LSAPI) GetSurveyProperties(surveyID int) (map[string]interface{}, error) {
	cmd := &Command{
		Method: "get_survey_properties",
		Params: []interface{}{
			api.SessionKey,
			surveyID,
		},
	}
	result, err := cmd.Execute(api.Url)
	if err != nil {
		return nil, err
	}
	return result.Result.(map[string]interface{}), nil
}

func (api *LSAPI) SetSurveyProperties(surveyID int, properties ...interface{}) (map[string]interface{}, error) {
	cmd := &Command{
		Method: "set_survey_properties",
		Params: append(
			[]interface{}{
				api.SessionKey,
				surveyID,
			}, properties...),
	}
	result, err := cmd.Execute(api.Url)
	if err != nil {
		return nil, err
	}

	return result.Result.(map[string]interface{}), nil
}

func (api *LSAPI) GetUploadedFiles(surveyID int, sToken string) ([]UploadedFile, error) {
	cmd := &Command{
		Method: "get_uploaded_files",
		Params: []interface{}{
			api.SessionKey,
			surveyID,
			sToken,
		},
	}
	result, err := cmd.Execute(api.Url)
	if err != nil {
		return nil, err
	}

	files := make([]UploadedFile, 0)

	if l, ok := result.Result.([]interface{}); ok && len(l) == 0 {
		return files, nil
	}

	for _, f := range result.Result.(map[string]interface{}) {
		content := f.(map[string]interface{})["content"].(string)
		dec, err := base64.StdEncoding.DecodeString(content)
		if err != nil {
			return nil, err
		}
		files = append(files, UploadedFile{
			Filename: f.(map[string]interface{})["meta"].(map[string]interface{})["name"].(string),
			Content:  dec,
		})
	}

	return files, nil
}