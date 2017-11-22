package lsapi

import (
	"encoding/base64"
	"encoding/csv"
	"strings"
	"testing"

	"github.com/remogatto/prettytest"
	"gogs.carducci-dante.gov.it/andrea.fazzi/karmen/config"
)

var api *LSAPI

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
	api, err = New(config.LSAPIRemoteControlUrl, config.LSAPIUsername, config.LSAPIPassword)
	if err != nil {
		panic(err)
	}

}

func (t *testSuite) TestGetSessionKey() {
	t.True(api.SessionKey != "")
}

func (t *testSuite) TestWrongCredentials() {
	_, err := New(config.LSAPIRemoteControlUrl, config.LSAPIUsername, "wrong")
	t.Not(t.Nil(err))
}

func (t *testSuite) TestExportResponses() {
	resp, err := api.ExportResponses(537264, "csv")
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
	ps, err := api.ListParticipants(241646, 0, 10, false, []string{"attribute_1", "attribute_2"})
	t.Nil(err)
	t.Equal("Andrea", ps["MCLhkEghQYSDoXq"].Firstname)
	t.Equal("Foo 1", ps["MCLhkEghQYSDoXq"].Attributes["attribute_1"].(string))
	t.Equal("Foo 3", ps["dWjgf5bJ8uNagwT"].Attributes["attribute_1"].(string))
}

func (t *testSuite) TestGetSurveyProperties() {
	result, err := api.GetSurveyProperties(537264)
	t.Nil(err)
	t.Equal("Y", result["active"].(string))
}

func (t *testSuite) TestSetSurveyProperties() {
	result, err := api.SetSurveyProperties(537264,
		map[string]interface{}{"expires": "2017-10-20 12:20:00"})
	t.Nil(err)
	t.True(result["expires"].(bool))
	result, err = api.SetSurveyProperties(537264,
		map[string]interface{}{"expires": nil})
	t.Nil(err)
	t.True(result["expires"].(bool))
}
