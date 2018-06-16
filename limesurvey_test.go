package limesurvey

import (
	"encoding/base64"
	"encoding/csv"
	"strings"
	"testing"

	"github.com/remogatto/prettytest"
)

var (
	api      *LSAPI
	url      string
	username string
	password string
)

type testSuite struct {
	prettytest.Suite
}

func TestRunner(t *testing.T) {
	prettytest.Run(
		t,
		new(testSuite),
	)
}

func (t *testSuite) BeforeAll() {
	var err error

	url = "http://localhost:18080/index.php/admin/remotecontrol"
	username = "admin"
	password = "admin"

	api, err = New(url, username, password)
	if err != nil {
		panic(err)
	}

}

func (t *testSuite) TestGetSessionKey() {
	t.True(api.SessionKey != "")
}

func (t *testSuite) TestWrongCredentials() {
	_, err := New(url, username, "wrong")
	t.Not(t.Nil(err))
}

func (t *testSuite) TestExportResponses() {
	resp, err := api.ExportResponses(181911, "csv")
	t.Nil(err)
	if err == nil {
		t.True(resp != "")
	}
	decoded, err := base64.StdEncoding.DecodeString(resp)
	t.Nil(err)
	if err == nil {
		r := csv.NewReader(strings.NewReader(string(decoded)))
		records, err := r.ReadAll()
		t.Nil(err)
		t.Equal("Andrea", records[1][4])
	}
}

func (t *testSuite) TestListParticipants() {
	ps, err := api.ListParticipants(297751, 0, 10, false, []string{"attribute_1", "attribute_2"})
	t.Nil(err)
	t.Equal("John", ps["eRWln1b85xEShOQ"].Firstname)
	t.Equal("Foo 1", ps["eRWln1b85xEShOQ"].Attributes["attribute_1"].(string))
	t.Equal("Foo 3", ps["0ZBh2Yz6hd6OrPN"].Attributes["attribute_1"].(string))

	ps, err = api.ListParticipants(297751, 0, 10, false)
	t.Nil(err)
}

func (t *testSuite) TestGetSurveyProperties() {
	result, err := api.GetSurveyProperties(181911)
	t.Nil(err)
	t.Equal("Y", result["active"].(string))
}

func (t *testSuite) TestSetSurveyProperties() {
	result, err := api.SetSurveyProperties(181911,
		map[string]interface{}{"expires": "2017-10-20 12:20:00"})
	t.Nil(err)
	t.True(result["expires"].(bool))
	result, err = api.SetSurveyProperties(181911,
		map[string]interface{}{"expires": nil})
	t.Nil(err)
	t.True(result["expires"].(bool))
}

func (t *testSuite) TestGetUploadedFiles() {
	ps, err := api.ListParticipants(195163, 0, 2, false)
	t.Nil(err)

	for token, _ := range ps {
		files, err := api.GetUploadedFiles(195163, token)
		if len(files) > 0 {
			t.Nil(err)
			t.Equal("foo.txt", files[0].Filename)
			t.Equal("Foo Bar\n", string(files[0].Content))
		}
	}
}
