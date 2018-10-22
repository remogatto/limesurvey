package limesurvey_test

import (
	"encoding/base64"
	"encoding/csv"
	"fmt"
	"strings"
	"testing"
	"time"

	ls "github.com/remogatto/limesurvey"
	"github.com/remogatto/prettytest"
)

var (
	client   *ls.Client
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

	url = "http://localhost:3005/index.php/admin/remotecontrol"
	username = "admin"
	password = "admin"

	// FIXME: Wait for the services to go up...
	time.Sleep(time.Second * 8)

	client, err = ls.New(url, username, password)
	if err != nil {
		panic(err)
	}

}

func (t *testSuite) TestGetSessionKey() {
	t.True(client.SessionKey != "")
}

func (t *testSuite) TestWrongCredentials() {
	_, err := ls.New(url, username, "wrong")
	t.Not(t.Nil(err))
}

func (t *testSuite) TestExportResponses() {
	resp, err := client.ExportResponses(181911, "csv")
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
	ps, err := client.ListParticipants(297751, 0, 10, false, []string{"attribute_1", "attribute_2"})
	t.Nil(err)
	// t.Equal("John", ps["eRWln1b85xEShOQ"].Firstname)
	// t.Equal("Foo 1", ps["eRWln1b85xEShOQ"].Attributes["attribute_1"].(string))
	// t.Equal("Foo 3", ps["0ZBh2Yz6hd6OrPN"].Attributes["attribute_1"].(string))

	t.Equal("John", ps[0].Firstname)
	t.Equal("Foo 1", ps[0].Attributes["attribute_1"].(string))
	t.Equal("Foo 3", ps[1].Attributes["attribute_1"].(string))

	ps, err = client.ListParticipants(297751, 0, 10, false)
	t.Nil(err)
}

func (t *testSuite) TestAddRemoveParticipants() {
	ps, err := client.AddParticipants(297751, []map[string]string{
		map[string]string{
			"firstname":   "James",
			"lastname":    "Bond",
			"email":       "james.bond@foo.org",
			"attribute_1": "Foo 5",
			"attribute_2": "Foo 6",
		},
	})

	t.Nil(err)
	t.Equal(1, len(ps))

	for _, p := range ps {
		err = client.RemoveParticipants(297751, []string{p.TokenID})
	}

	ps, err = client.ListParticipants(297751, 0, 10, false)
	t.Equal(2, len(ps))

	// Test returned error
	err = client.RemoveParticipants(297751, []string{"4", "5"})
	t.Not(t.Nil(err))

}

func (t *testSuite) TestGetParticipantProperties() {
	t.Pending()
	// prop, err := client.GetParticipantProperties(297751, "1")
	// t.Nil(err)
}

func (t *testSuite) TestInviteParticipants() {
	ps, err := client.AddParticipants(297751, []map[string]string{
		map[string]string{
			"firstname":   "James",
			"lastname":    "Bond",
			"email":       "james.bond@foo.org",
			"attribute_1": "Foo 5",
			"attribute_2": "Foo 6",
		},
	})
	t.Nil(err)

	// FIXME: not sure if really tested
	for _, p := range ps {
		err := client.InviteParticipants(297751, []string{p.TokenID})
		t.False(strings.Contains(fmt.Sprintf("%s", err), "error"))
	}

}

func (t *testSuite) TestGetSurveyProperties() {
	result, err := client.GetSurveyProperties(181911)
	t.Nil(err)
	t.Equal("Y", result["active"].(string))
}

func (t *testSuite) TestSetSurveyProperties() {
	result, err := client.SetSurveyProperties(181911,
		map[string]interface{}{"expires": "2017-10-20 12:20:00"})
	t.Nil(err)
	t.True(result["expires"].(bool))
	result, err = client.SetSurveyProperties(181911,
		map[string]interface{}{"expires": nil})
	t.Nil(err)
	t.True(result["expires"].(bool))
}

func (t *testSuite) TestGetUploadedFiles() {
	ps, err := client.ListParticipants(195163, 0, 2, false)
	t.Nil(err)

	for _, p := range ps {
		files, err := client.GetUploadedFiles(195163, p.Token)
		if len(files) > 0 {
			t.Nil(err)
			t.Equal("foo.txt", files[0].Filename)
			t.Equal("Foo Bar\n", string(files[0].Content))
		}
	}
}
